<?php

namespace App\Console\Commands;

use App\Domain\BackupService;
use App\Domain\CommandBus;
use App\Domain\DomainError;
use App\Domain\Money;
use App\Domain\WorkspaceDocument;
use App\Migration\ExactJson;
use App\Migration\LegacyNorocelAdapter;
use App\Migration\SupabaseExportAdapter;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class MigrateNorocel extends Command
{
    protected $signature = 'norocel:migrate {file} {--user= : Existing destination Laravel UUID} {--source-user= : Owner UUID in server export} {--month= : Explicit legacy budget month YYYY-MM} {--apply : Replace destination on a non-production database} {--report= : Save JSON report}';

    protected $description = 'Read-only dry run or atomic test migration; never modifies the Supabase source';

    public function handle(BackupService $backup, WorkspaceDocument $validator, LegacyNorocelAdapter $adapter, CommandBus $bus): int
    {
        $run = (string) Str::uuid();
        $report = ['run_id' => $run, 'source_commit' => '25d3a31', 'live_source_verified' => false, 'blockers' => []];
        $assumptions = ['month' => $this->option('month'), 'source_user' => $this->option('source-user')];
        $report['conversion_assumptions'] = $assumptions;
        try {
            $path = $this->argument('file');
            if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
                throw new DomainError('BACKUP_LIMIT_EXCEEDED');
            }
            $text = file_get_contents($path);
            $hash = hash('sha256', $text);
            $report['source_sha256'] = $hash;
            $source = ExactJson::decode($text);
            if (($source['format'] ?? null) === 'norocel.supabase-export') {
                if (! $this->option('source-user')) {
                    throw new DomainError('SOURCE_OWNER_REQUIRED');
                } $source = app(SupabaseExportAdapter::class)->selectOwner($source, $this->option('source-user'));
            }
            if (! isset($source['schema'])) {
                $adapted = $adapter->convert($source, $this->option('month'));
                $document = $adapted['document'];
                $report['warnings'] = $adapted['warnings'];
                $report['account_reconciliation'] = $adapted['reconciliation'];
            } else {
                $document = $source;
                $report['warnings'] = [];
            }
            $report['manifest'] = $validator->validate($document);
            $types = [];
            $months = [];
            foreach ($document['operations'] as $op) {
                $types[$op['type']] = ($types[$op['type']] ?? 0) + 1;
                if (in_array($op['type'], ['income', 'expense']) && $op['status'] === 'posted') {
                    $key = substr($op['occurred_on'], 0, 7).':'.$op['currency_code'].':'.$op['type'];
                    $months[$key] = Money::sum([$months[$key] ?? '0', $op['amount_minor']]);
                }
            }
            $report['operation_counts_by_type'] = $types;
            $report['monthly_cash_flow'] = $months;
            $report['transfer_sides'] = array_values(array_filter($document['operations'], fn ($o) => in_array($o['type'], ['transfer', 'exchange'])));
            $report['mode'] = $this->option('apply') ? 'apply' : 'dry-run';
            if ($this->option('apply')) {
                if (app()->environment('production')) {
                    throw new DomainError('PRODUCTION_MIGRATION_REQUIRES_CUTOVER_RUNBOOK');
                }
                $user = $this->option('user');
                if (! $user || ! User::find($user)) {
                    throw new DomainError('DESTINATION_USER_REQUIRED');
                }
                $existing = DB::table('migration_runs')->where('user_id', $user)->where('source_hash', $hash)->first();
                $key = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $user.':'.$hash);
                if ($existing) {
                    $report = json_decode($existing->report, true, 512, JSON_THROW_ON_ERROR);
                    $previous = $report['conversion_assumptions'] ?? ['month' => collect($report['warnings'])->firstWhere('code', 'LEGACY_BUDGET_ASSUMPTION')['month'] ?? null, 'source_user' => null];
                    if ($previous !== $assumptions) {
                        throw new DomainError('MIGRATION_ASSUMPTION_CONFLICT', 409);
                    }
                    $result=$bus->execute($user,$key,'legacy-migration',['source_hash'=>$hash],fn()=>throw new DomainError('MIGRATION_LOG_INCONSISTENT',409));
                    $report['workspace_revision']=$result['meta']['workspace_revision'];
                    $report['replayed'] = true;
                } else {
                    $preview = $backup->preview($user, json_encode($document, JSON_THROW_ON_ERROR));
                    $result = $bus->execute($user, $key, 'legacy-migration', ['source_hash' => $hash], function () use ($backup, $preview, $user, $hash, &$report, $run, $document) {
                        $result = $backup->apply($user, ['preview_token' => $preview['preview_token'], 'expected_workspace_revision' => $preview['expected_workspace_revision']]);
                        $report['id_remapping'] = $result['id_remapping'];
                        $report['goal_projections'] = $result['dashboard']['goals'];
                        $report['previous_backup_id'] = $result['previous_backup_id'];
                        DB::table('migration_runs')->insert(['id' => $run, 'user_id' => $user, 'source_hash' => $hash, 'report' => json_encode($report, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                        foreach (WorkspaceDocument::TABLES as $table) {
                            foreach ($document[$table] as $r) {
                                DB::table('migration_id_map')->insert(['run_id' => $run, 'entity' => $table, 'source_id' => $r['id'], 'target_id' => $result['id_remapping'][$r['id']] ?? $r['id']]);
                            }
                        }

                        return $result;
                    });
                    $report['workspace_revision'] = $result['meta']['workspace_revision'];
                }
            }
        } catch (\Throwable $e) {
            $report['blockers'][] = ['code' => $e instanceof DomainError ? $e->errorCode : 'VALIDATION_OR_STORAGE_FAILED', 'details' => $e instanceof DomainError ? $e->details : []];
        }
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($this->option('report')) {
            file_put_contents($this->option('report'), $json);
        }
        $this->line($json);

        return $report['blockers'] ? self::FAILURE : self::SUCCESS;
    }
}
