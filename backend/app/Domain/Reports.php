<?php

namespace App\Domain;

use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class Reports
{
    public function __construct(private readonly FinancialEngine $engine, private readonly Projection $projection) {}

    public function run(string $user, string $kind, array $p): array
    {
        $timezone = DB::table('user_settings')->where('user_id', $user)->value('timezone');
        $month = $p['month'] ?? now($timezone)->format('Y-m');
        Validator::make(['month' => $month] + $p, ['month' => 'date_format:Y-m', 'date_from' => 'sometimes|date_format:Y-m-d', 'date_to' => 'sometimes|date_format:Y-m-d', 'currency_code' => 'sometimes|in:MDL,EUR,USD,RON', 'goal_only' => 'sometimes|boolean', 'exclude_settlements' => 'sometimes|boolean'])->validate();
        $to = $p['date_to'] ?? CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString();
        $from = $p['date_from'] ?? CarbonImmutable::parse($month.'-01')->subMonths(5)->toDateString();
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366) {
            throw new DomainError('INVALID_REPORT_PERIOD');
        }
        $result = ['date_from' => $from, 'date_to' => $to, 'generated_at' => now()->toIso8601String()];
        if ($kind === 'cash-flow') {
            $months = [];
            for ($d = CarbonImmutable::parse($from)->startOfMonth(); $d->toDateString() <= $to; $d = $d->addMonth()) {
                $months[] = ['month' => $d->format('Y-m'), 'currencies' => $this->projection->cashFlow($user, max($d->toDateString(), $from), min($d->endOfMonth()->toDateString(), $to))];
            }

            return $result + ['currencies' => $this->projection->cashFlow($user, $from, $to), 'months' => $months];
        }
        if ($kind === 'expenses') {
            $q = DB::table('operations')->where('user_id', $user)->where('type', 'expense')->where('status', 'posted')->whereBetween('occurred_on', [$from, $to]);
            if (isset($p['currency_code'])) {
                $q->where('currency_code', $p['currency_code']);
            }
            if ($p['goal_only'] ?? false) {
                $q->whereNotNull('goal_id');
            }
            if ($p['exclude_settlements'] ?? false) {
                $q->whereNotIn('id', DB::table('liability_settlements')->where('user_id', $user)->select('operation_id'));
            }
            $categories = [];
            foreach ($q->cursor() as $o) {
                $key = $o->category_id.':'.$o->currency_code;
                $row = $categories[$key] ?? ['category_id' => $o->category_id, 'currency_code' => $o->currency_code, 'amount_minor' => '0'];
                $row['amount_minor'] = Money::sum([$row['amount_minor'], (string) $o->amount_minor]);
                $categories[$key] = $row;
            }
            usort($categories, fn ($a, $b) => BigInteger::of($b['amount_minor'])->compareTo($a['amount_minor']));

            return $result + ['categories' => array_values($categories)];
        }
        $daily = [];
        foreach (DB::table('operations')->where('user_id', $user)->where('status', 'posted')->where('occurred_on', '<=', $to)->cursor() as $op) {
            $effects = $op->account_id ? [$op->account_id => $op->type === 'income' ? (string) $op->amount_minor : '-'.$op->amount_minor] : [$op->from_account_id => '-'.$op->amount_minor, $op->to_account_id => (string) $op->target_amount_minor];
            foreach ($effects as $id => $delta) {
                $daily[$id][$op->occurred_on] = Money::sum([$daily[$id][$op->occurred_on] ?? '0', $delta]);
            }
        }
        $accounts = [];
        foreach (DB::table('accounts')->where('user_id', $user)->when(isset($p['currency_code']), fn ($q) => $q->where('currency_code', $p['currency_code']))->get() as $a) {
            $balance = (string) $a->opening_balance_minor;
            foreach ($daily[$a->id] ?? [] as $day => $delta) {
                if ($day < $from) {
                    $balance = Money::sum([$balance, $delta]);
                }
            }
            $points = [];
            for ($d = CarbonImmutable::parse($from); $d->toDateString() <= $to; $d = $d->addDay()) {
                $balance = Money::sum([$balance, $daily[$a->id][$d->toDateString()] ?? '0']);
                $points[] = ['date' => $d->toDateString(), 'balance_minor' => $balance];
            }
            $accounts[] = ['account_id' => $a->id, 'name' => $a->name, 'currency_code' => $a->currency_code, 'points' => $points];
        }

        return $result + ['reconstructed_history' => true, 'accounts' => $accounts];
    }
}
