<?php

namespace App\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class Projection
{
    public function __construct(private readonly FinancialEngine $engine) {}

    public static function serialize(array|object $value): array
    {
        $row = (array) $value;
        foreach ($row as $key => &$v) {
            if ($v !== null && (str_ends_with($key, '_minor') || in_array($key, ['quoted_rate', 'effective_rate']))) {
                $v = (string) $v;
            }
            if (in_array($key, ['include_in_total', 'is_system', 'disabled', 'goal_completion_requested', 'is_admin'])) {
                $v = (bool) $v;
            }
            if ($v !== null && in_array($key, ['legacy_metadata', 'before_payload', 'after_payload']) && is_string($v)) {
                $v = json_decode($v, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        unset($v,$row['user_id'],$row['name_key']);

        return $row;
    }

    public function account(string $user, array $row): array
    {
        $goal = DB::table('goals')->where('user_id', $user)->where('savings_account_id', $row['id'])->value('id');

        return self::serialize($row) + ['balance_minor' => $this->engine->balance($user, $row['id']), 'goal_id' => $goal];
    }

    public function goal(string $user, array $row): array
    {
        $legacy = ! $row['savings_account_id'];
        $saved = $legacy ? (string) ($row['legacy_saved_minor'] ?? '0') : $this->engine->balance($user, $row['savings_account_id']);
        $ops = DB::table('operations')->where('user_id', $user)->where('goal_id', $row['id'])->where('status', 'posted')->orderBy('occurred_on')->orderBy('created_at')->orderBy('id')->get();
        $spent = BigInteger::zero();
        $completed = null;
        foreach ($ops as $op) {
            $spent = $spent->plus((string) $op->amount_minor);
            if (! $completed && ($op->goal_completion_requested || $spent->isGreaterThanOrEqualTo((string) $row['target_amount_minor']))) {
                $completed = $op->occurred_on;
            }
        }
        $funded = Money::sum([$saved, (string) $spent]);
        $status = $row['cancelled_at'] ? 'cancelled' : (($completed || $row['legacy_status'] === 'spent') ? 'spent' : (BigInteger::of($funded)->isGreaterThanOrEqualTo((string) $row['target_amount_minor']) ? 'reached' : 'active'));
        $progress = Money::percent($funded, (string) $row['target_amount_minor']);
        if (BigDecimal::of($progress)->isGreaterThan(100)) {
            $progress = '100.00';
        }

        return self::serialize($row) + ['saved_now_minor' => $saved, 'spent_on_goal_minor' => (string) $spent, 'funded_lifetime_minor' => $funded, 'progress' => $progress, 'status' => $status, 'completed_at' => $completed ?? $row['legacy_completed_at'], 'legacy_read_only' => $legacy];
    }

    public function liability(string $user, array $row): array
    {
        $link = DB::table('liability_settlements')->where('user_id', $user)->where('liability_id', $row['id'])->first();

        return self::serialize($row) + ['status' => $row['cancelled_at'] ? 'cancelled' : ($link ? 'settled' : 'open'), 'settlement_operation' => $link ? self::serialize($this->engine->owned('operations', $user, $link->operation_id)) : null];
    }

    public function budget(string $user, array $row): array
    {
        $end = CarbonImmutable::parse($row['period_month'])->endOfMonth()->toDateString();
        $groups = [];
        foreach (DB::table('operations')->where('user_id', $user)->where('status', 'posted')->where('type', 'expense')->where('category_id', $row['category_id'])->whereBetween('occurred_on', [$row['period_month'], $end])->cursor() as $op) {
            $groups[$op->currency_code] = Money::sum([$groups[$op->currency_code] ?? '0', (string) $op->amount_minor]);
        }
        $fact = $groups[$row['currency_code']] ?? '0';
        unset($groups[$row['currency_code']]);

        return self::serialize($row) + ['fact_minor' => $fact, 'remaining_minor' => Money::sum([(string) $row['limit_minor'], '-'.$fact]), 'progress' => Money::percent($fact, (string) $row['limit_minor']), 'other_currencies' => $groups];
    }

    public function cashFlow(string $user, string $from, string $to): array
    {
        $totals = array_fill_keys(Money::CURRENCIES, ['income_minor' => '0', 'expense_minor' => '0', 'net_minor' => '0']);
        foreach (DB::table('operations')->where('user_id', $user)->where('status', 'posted')->whereIn('type', ['income', 'expense'])->whereBetween('occurred_on', [$from, $to])->cursor() as $op) {
            $key = $op->type.'_minor';
            $totals[$op->currency_code][$key] = Money::sum([$totals[$op->currency_code][$key], (string) $op->amount_minor]);
        }
        foreach ($totals as &$t) {
            $t['net_minor'] = Money::sum([$t['income_minor'], '-'.$t['expense_minor']]);
        }

        return $totals;
    }

    public function liabilityTotals(string $user): array
    {
        $totals = array_fill_keys(Money::CURRENCIES, ['receivable_minor' => '0', 'payable_minor' => '0', 'credit_minor' => '0']);
        foreach (DB::table('liabilities')->where('user_id', $user)->whereNull('cancelled_at')->whereNotIn('id', DB::table('liability_settlements')->where('user_id', $user)->select('liability_id'))->cursor() as $l) {
            $key = $l->kind.'_minor';
            $totals[$l->currency_code][$key] = Money::sum([$totals[$l->currency_code][$key], (string) $l->principal_minor]);
        }

        return $totals;
    }

    public function dashboard(string $user, string $month): array
    {
        $start = $month.'-01';
        $end = CarbonImmutable::parse($start)->endOfMonth()->toDateString();
        $totals = array_fill_keys(Money::CURRENCIES, ['total_minor' => '0', 'available_minor' => '0', 'savings_minor' => '0']);
        foreach (DB::table('accounts')->where('user_id', $user)->cursor() as $a) {
            if ($a->include_in_total) {
                $b = $this->engine->balance($user, $a->id);
                $t = &$totals[$a->currency_code];
                $t['total_minor'] = Money::sum([$t['total_minor'], $b]);
                $key = $a->kind === 'regular' ? 'available_minor' : 'savings_minor';
                $t[$key] = Money::sum([$t[$key], $b]);
                unset($t);
            }
        }

        return ['month' => $month, 'balances' => $totals, 'cash_flow' => $this->cashFlow($user, $start, $end), 'recent_operations' => DB::table('operations')->where('user_id', $user)->orderByDesc('occurred_on')->orderByDesc('created_at')->orderByDesc('id')->limit(8)->get()->map(self::serialize(...))->all(), 'goals' => DB::table('goals')->where('user_id', $user)->whereNull('cancelled_at')->orderByDesc('updated_at')->limit(6)->get()->map(fn ($g) => $this->goal($user, (array) $g))->all(), 'budgets' => DB::table('budgets')->where('user_id', $user)->where('period_month', $start)->where('disabled', false)->limit(6)->get()->map(fn ($b) => $this->budget($user, (array) $b))->all(), 'liabilities' => DB::table('liabilities')->where('user_id', $user)->whereNull('cancelled_at')->whereNotIn('id', DB::table('liability_settlements')->where('user_id', $user)->select('liability_id'))->orderBy('due_on')->limit(6)->get()->map(fn ($l) => $this->liability($user,(array) $l))->all()];
    }
}
