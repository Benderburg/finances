<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FxAnalytics
{
    public function __construct(private readonly ReferenceRates $fx, private readonly FinancialEngine $engine) {}

    public function flow(string $user, string $from, string $to, string $currency, array $context = []): array
    {
        $rows = DB::table('operations')->where('user_id', $user)->where('status', 'posted')->whereIn('type', ['income', 'expense'])->whereBetween('occurred_on', [$from, $to])->get()->map(fn ($o) => ['bucket' => $o->type.'_minor', 'amount_minor' => (string) $o->amount_minor, 'currency_code' => $o->currency_code, 'date' => $o->occurred_on, 'operation_id' => $o->id]);
        $a = $this->fx->aggregate($rows, $currency, $context + ['date_from' => $from, 'date_to' => $to]);
        $a['known_subtotal'] += ['income_minor' => '0', 'expense_minor' => '0'];
        $a['known_subtotal']['net_minor'] = Money::sum([$a['known_subtotal']['income_minor'], '-'.$a['known_subtotal']['expense_minor']]);

        return $a;
    }

    public function dashboard(string $user, array $data, object $settings, array $p): array
    {
        $currency = $p['display_currency'] ?? $settings->base_currency_code;
        $date = $p['valuation_date'] ?? now($settings->timezone)->toDateString();
        $rows = [];
        foreach (DB::table('accounts')->where('user_id', $user)->where('include_in_total', true)->get() as $a) {
            $row = ['amount_minor' => $this->engine->balance($user, $a->id), 'currency_code' => $a->currency_code, 'date' => $date, 'account_id' => $a->id];
            $rows[] = $row + ['bucket' => 'total_minor'];
            $rows[] = $row + ['bucket' => $a->kind === 'regular' ? 'available_minor' : 'savings_minor'];
        }
        $context = ['workspace_revision' => (string) $settings->workspace_revision, 'valuation_date' => $date];
        $balance = $this->fx->aggregate($rows, $currency, $context);
        $balance['known_subtotal'] += ['total_minor' => '0', 'available_minor' => '0', 'savings_minor' => '0'];
        $start = $data['month'].'-01';

        return $data + ['valuation_date' => $date, 'consolidated' => ['balances' => $balance, 'cash_flow' => $this->flow($user, $start, CarbonImmutable::parse($start)->endOfMonth()->toDateString(), $currency, $context)]];
    }

    public function report(string $user, string $kind, array $data, object $settings, array $p): array
    {
        $currency = $p['display_currency'] ?? $settings->base_currency_code;
        $context = ['workspace_revision' => (string) $settings->workspace_revision, 'date_from' => $data['date_from'], 'date_to' => $data['date_to'], 'filters' => $p];
        if ($kind === 'cash-flow') {
            $data['consolidated'] = $this->flow($user, $data['date_from'], $data['date_to'], $currency, $context);
            foreach ($data['months'] as &$m) {
                $start = max($m['month'].'-01', $data['date_from']);
                $end = min(CarbonImmutable::parse($m['month'].'-01')->endOfMonth()->toDateString(), $data['date_to']);
                $m['consolidated'] = $this->flow($user, $start, $end, $currency, $context);
            }
            unset($m);
        } elseif ($kind === 'expenses') {
            $q = DB::table('operations')->where('user_id', $user)->where('status', 'posted')->where('type', 'expense')->whereBetween('occurred_on', [$data['date_from'], $data['date_to']]);
            if (isset($p['currency_code'])) {
                $q->where('currency_code', $p['currency_code']);
            }
            if ($p['goal_only'] ?? false) {
                $q->whereNotNull('goal_id');
            }
            if ($p['exclude_settlements'] ?? false) {
                $q->whereNotIn('id', DB::table('liability_settlements')->where('user_id', $user)->select('operation_id'));
            }
            $rows = $q->get()->map(fn ($o) => ['bucket' => $o->category_id, 'amount_minor' => (string) $o->amount_minor, 'currency_code' => $o->currency_code, 'date' => $o->occurred_on, 'operation_id' => $o->id]);
            $data['consolidated'] = $this->fx->aggregate($rows, $currency, $context);
        } else {
            $points = [];
            foreach ($data['accounts'] as $account) {
                foreach ($account['points'] as $pnt) {
                    $points[$pnt['date']][] = ['amount_minor' => $pnt['balance_minor'], 'currency_code' => $account['currency_code'], 'date' => $pnt['date'], 'account_id' => $account['account_id']];
                }
            }
            $data['consolidated_points'] = array_map(fn ($date, $rows) => ['date' => $date, 'valuation' => $this->fx->aggregate($rows, $currency, $context + ['valuation_date' => $date])], array_keys($points), array_values($points));
        }

        return $data;
    }
}
