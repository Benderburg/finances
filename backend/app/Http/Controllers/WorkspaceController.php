<?php

namespace App\Http\Controllers;

use App\Domain\BackupService;
use App\Domain\CommandBus;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\FinancialEngine;
use App\Domain\FxAnalytics;
use App\Domain\Projection;
use App\Domain\Reports;
use App\Domain\WorkspaceService;
use App\Http\Requests\CommandRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkspaceController extends Controller
{
    public function __construct(private readonly CommandBus $bus, private readonly WorkspaceService $workspace, private readonly FinancialEngine $engine, private readonly Projection $projection) {}

    public function me(Request $r)
    {
        return $this->bus->snapshot($r->user()->id, fn ($s) => ['user' => $r->user()->fresh(), 'settings' => (array) $s, 'pending_email' => $r->user()->pending_email]);
    }

    public function profile(CommandRequest $r)
    {
        $p = Fields::check($r->all(), ['full_name' => 'sometimes|string|max:255', 'avatar_url' => 'nullable|url:http,https|max:2048', 'locale' => 'sometimes|in:ro,ru,en', 'base_currency_code' => 'sometimes|in:MDL,EUR,USD,RON', 'theme' => 'sometimes|in:light,dark,system', 'timezone' => 'sometimes|timezone']);

        return $this->bus->execute($r->user()->id, $r->header('Idempotency-Key', ''), 'profile', $p, function () use ($r, $p) {
            DB::table('users')->where('id', $r->user()->id)->update(array_intersect_key($p, array_flip(['full_name', 'avatar_url'])) + ['updated_at' => now()]);
            $settings = array_intersect_key($p, array_flip(['locale', 'base_currency_code', 'theme', 'timezone']));
            if ($settings) {
                DB::table('user_settings')->where('user_id', $r->user()->id)->update($settings);
            }

            return ['user' => $r->user()->fresh(), 'settings' => (array) DB::table('user_settings')->where('user_id', $r->user()->id)->first()];
        });
    }

    public function dashboard(Request $r)
    {
        $month = $r->query('month', now(DB::table('user_settings')->where('user_id', $r->user()->id)->value('timezone'))->format('Y-m'));
        $r->validate(['month' => 'sometimes|date_format:Y-m', 'display_currency' => 'sometimes|in:MDL,EUR,USD,RON', 'valuation_date' => 'sometimes|date_format:Y-m-d']);

        return $this->bus->snapshot($r->user()->id, fn ($s) => app(FxAnalytics::class)->dashboard($r->user()->id, $this->projection->dashboard($r->user()->id, $month), $s, $r->query()));
    }

    public function index(Request $r, string $resource)
    {
        $table = str_replace('-', '_', $resource);
        $user = $r->user()->id;
        $r->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100', 'month' => 'sometimes|date_format:Y-m', 'date_from' => 'sometimes|date_format:Y-m-d', 'date_to' => 'sometimes|date_format:Y-m-d', 'q' => 'sometimes|string|max:255', 'status' => 'sometimes|in:posted,voided', 'type' => 'sometimes|in:income,expense,transfer,exchange', 'archive' => 'sometimes|in:active,archived,all']);

        return $this->bus->snapshot($user, function () use ($r, $table, $user) {
            $q = DB::table($table)->where('user_id', $user);
            if ($table === 'operations') {
                foreach (['account_id', 'category_id', 'goal_id', 'liability_id'] as $f) {
                    if ($r->filled($f)) {
                        $this->engine->owned(match ($f) {
                            'account_id' => 'accounts','category_id' => 'categories','goal_id' => 'goals','liability_id' => 'liabilities'
                        }, $user, $r->query($f));
                    }
                }
                if ($r->filled('account_id')) {
                    $q->where(fn ($b) => $b->where('account_id', $r->query('account_id'))->orWhere('from_account_id', $r->query('account_id'))->orWhere('to_account_id', $r->query('account_id')));
                }
                foreach (['type', 'category_id', 'goal_id', 'status'] as $f) {
                    if ($r->filled($f)) {
                        $q->where($f, $r->query($f));
                    }
                }
                if ($r->filled('liability_id')) {
                    $q->whereIn('id', DB::table('liability_settlements')->where('user_id', $user)->where('liability_id', $r->query('liability_id'))->select('operation_id'));
                }
                if ($r->filled('date_from')) {
                    $q->where('occurred_on', '>=', $r->query('date_from'));
                } if ($r->filled('date_to')) {
                    $q->where('occurred_on', '<=', $r->query('date_to'));
                }
                if ($r->filled('q')) {
                    $q->where('description', 'like', '%'.$r->query('q').'%');
                } $q->orderByDesc('occurred_on');
            } elseif ($table === 'budgets') {
                $month = $r->query('month', now(DB::table('user_settings')->where('user_id', $user)->value('timezone'))->format('Y-m'));
                $q->where('period_month', $month.'-01');
            } elseif (in_array($table, ['accounts', 'categories'])) {
                if ($r->query('archive', 'all') === 'active') {
                    $q->whereNull('archived_at');
                } if ($r->query('archive') === 'archived') {
                    $q->whereNotNull('archived_at');
                } if ($r->filled('kind')) {
                    $q->where('kind', $r->query('kind'));
                }
            }
            $page = $q->orderByDesc('created_at')->orderByDesc('id')->paginate((int) $r->query('per_page', 30));

            return ['items' => $page->getCollection()->map(function ($row) use ($user, $table) {
                $data = $this->workspace->project($user, $table, (array) $row);
                if ($table === 'operations') {
                    $data['liability_id'] = DB::table('liability_settlements')->where('user_id', $user)->where('operation_id', $row->id)->value('liability_id');
                }

                return $data;
            })->all(), 'pagination' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()]] + ($table === 'liabilities' ? ['totals' => $this->projection->liabilityTotals($user)] : []);
        });
    }

    public function show(Request $r, string $resource, string $id)
    {
        $table = str_replace('-', '_', $resource);
        $user = $r->user()->id;

        return $this->bus->snapshot($user, function () use ($table, $user, $id) {
            $data = $this->workspace->project($user, $table, $this->engine->owned($table, $user, $id));
            if ($table === 'operations') {
                $data['history'] = DB::table('operation_revisions')->where('user_id', $user)->where('operation_id', $id)->orderBy('created_at')->get()->map(Projection::serialize(...))->all();
                $data['liability_id'] = DB::table('liability_settlements')->where('user_id', $user)->where('operation_id', $id)->value('liability_id');
            }

            return $data;
        });
    }

    public function mutate(CommandRequest $r, string $resource, ?string $id = null, ?string $action = null)
    {
        $table = str_replace('-', '_', $resource);
        $user = $r->user()->id;
        $p = $r->all();
        $action ??= match ($r->method()) {
            'POST' => 'create','PATCH' => 'update','DELETE' => 'delete'
        };

        return $this->bus->execute($user, $r->header('Idempotency-Key', ''), $table.':'.$action.':'.$id, $p, function () use ($table, $action, $id, $p, $user) {
            if ($table === 'operations') {
                return $this->workspace->operation($user, $action, $id, $p);
            } if ($table === 'goals' && $action === 'spend') {
                return $this->workspace->spend($user, $id, $p);
            } if ($table === 'liabilities' && in_array($action, ['settle', 'void-settlement'])) {
                return $this->workspace->settle($user, $id, $p, $action === 'void-settlement');
            } if ($table === 'budgets' && $action === 'ensure-month') {
                return $this->workspace->ensureMonth($user, $p);
            }

            return $this->workspace->resource($user, $table, $action, $id, $p);
        });
    }

    public function amendGoalExpense(CommandRequest $r, string $id, string $operation)
    {
        return $this->bus->execute($r->user()->id, $r->header('Idempotency-Key', ''), 'goal-expense:'.$id.':'.$operation, $r->all(), fn () => $this->workspace->spend($r->user()->id, $id, $r->all(), $operation));
    }

    public function report(Request $r, string $kind, Reports $reports)
    {
        $r->validate(['display_currency' => 'sometimes|in:MDL,EUR,USD,RON']);

        return $this->bus->snapshot($r->user()->id, fn ($s) => app(FxAnalytics::class)->report($r->user()->id, $kind, $reports->run($r->user()->id, $kind, $r->query()), $s, $r->query()));
    }

    public function export(Request $r, BackupService $backup)
    {
        $data = $this->bus->snapshot($r->user()->id, fn () => $backup->export($r->user()->id));

        return response()->json($data['data'])->header('Content-Disposition', 'attachment; filename="norocel-backup-'.now()->toDateString().'.json"');
    }

    public function preview(Request $r, BackupService $backup)
    {
        $r->validate(['file' => 'required|file|max:10240', 'legacy_month' => 'nullable|date_format:Y-m']);

        return ['data' => $backup->preview($r->user()->id, file_get_contents($r->file('file')->getRealPath()), $r->input('legacy_month'))];
    }

    public function apply(CommandRequest $r, BackupService $backup)
    {
        return $this->bus->execute($r->user()->id, $r->header('Idempotency-Key', ''), 'restore', $r->all(), fn () => $backup->apply($r->user()->id, $r->all()));
    }

    public function previousBackup(Request $r, string $id)
    {
        $backup = DB::table('restore_backups')->where('user_id', $r->user()->id)->where('id', $id)->first();
        if (! $backup) {
            throw new DomainError('NOT_FOUND', 404);
        }

        return response($backup->payload)->header('Content-Type', 'application/json')->header('Content-Disposition', 'attachment; filename="norocel-before-restore.json"');
    }
}
