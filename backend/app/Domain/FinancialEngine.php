<?php

namespace App\Domain;

use App\Models\FinancialRecord;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinancialEngine
{
    public function owned(string $table, string $user, string $id): array
    {
        $row = DB::table($table)->where('user_id', $user)->where('id', $id)->first();
        if (! $row) {
            throw new DomainError('NOT_FOUND', 404);
        }
        $model = new FinancialRecord;
        $model->setTable($table);
        $model->setRawAttributes((array) $row);
        if (auth()->check()) {
            Gate::authorize('view', $model);
        }

        return (array) $row;
    }

    public function balance(string $user, string $id, ?string $date = null): string
    {
        $account = $this->owned('accounts', $user, $id);
        $sum = BigInteger::of($account['opening_balance_minor']);
        $query = DB::table('operations')->where('user_id', $user)->where('status', 'posted')->where(fn ($q) => $q->where('account_id', $id)->orWhere('from_account_id', $id)->orWhere('to_account_id', $id));
        if ($date) {
            $query->where('occurred_on', '<=', $date);
        }
        foreach ($query->cursor() as $op) {
            if ($op->account_id === $id) {
                $sum = $sum->plus($op->type === 'income' ? (string) $op->amount_minor : '-'.$op->amount_minor);
            }
            if ($op->from_account_id === $id) {
                $sum = $sum->minus((string) $op->amount_minor);
            }
            if ($op->to_account_id === $id) {
                $sum = $sum->plus((string) $op->target_amount_minor);
            }
        }

        return (string) $sum;
    }

    public function verifyBalances(string $user, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        sort($ids);
        DB::table('accounts')->where('user_id', $user)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $result = [];
        foreach ($ids as $id) {
            $account = $this->owned('accounts', $user, $id);
            $balance = $this->balance($user, $id);
            $number = BigInteger::of($balance);
            if ($number->isNegative()) {
                throw new DomainError('INSUFFICIENT_FUNDS', 409, ['account_id' => $id, 'currency_code' => $account['currency_code'], 'would_be_balance_minor' => $balance]);
            }
            if ($number->isGreaterThan(Money::MAX)) {
                throw new DomainError('BALANCE_OUT_OF_RANGE', 409);
            }
            $result[] = ['account_id' => $id, 'currency_code' => $account['currency_code'], 'balance_minor' => $balance];
        }

        return $result;
    }

    public static function accountIds(array $op): array
    {
        return [$op['account_id'] ?? null, $op['from_account_id'] ?? null, $op['to_account_id'] ?? null];
    }

    public function normalize(string $user, array $data, ?array $old = null, bool $goalCommand = false): array
    {
        $d = array_replace(['account_id' => null, 'from_account_id' => null, 'to_account_id' => null, 'category_id' => null, 'goal_id' => null, 'goal_completion_requested' => false, 'target_amount_minor' => null, 'target_currency_code' => null, 'quoted_rate' => null, 'effective_rate' => null, 'description' => ''], $data);
        Validator::make($d, ['type' => 'required|in:income,expense,transfer,exchange', 'occurred_on' => 'required|date_format:Y-m-d', 'description' => 'string|max:2000', 'goal_completion_requested' => 'boolean'])->validate();
        $amount = Money::minor($d['amount_minor'] ?? null);
        $single = in_array($d['type'], ['income', 'expense']);
        if ($old && $single !== in_array($old['type'], ['income', 'expense'])) {
            throw new DomainError('OPERATION_TYPE_IMMUTABLE');
        }
        if ($old && $single && $old['type'] !== $d['type']) {
            throw new DomainError('OPERATION_TYPE_IMMUTABLE');
        }
        if ($single) {
            if (! $d['account_id'] || ! $d['category_id'] || $d['from_account_id'] || $d['to_account_id'] || $d['target_amount_minor'] || $d['target_currency_code'] || $d['quoted_rate'] || $d['effective_rate']) {
                throw new DomainError('INVALID_OPERATION_SHAPE');
            }
            $account = $this->owned('accounts', $user, $d['account_id']);
            $category = $this->owned('categories', $user, $d['category_id']);
            if ($category['kind'] !== $d['type']) {
                throw new DomainError('CATEGORY_TYPE_MISMATCH');
            }
            if ($category['archived_at'] && (! $old || $old['category_id'] !== $category['id'])) {
                throw new DomainError('CATEGORY_ARCHIVED');
            }
            if ($category['system_code'] === 'goal_expense' && (! $goalCommand || ! $d['goal_id'])) {
                throw new DomainError('GOAL_COMMAND_REQUIRED');
            }
            if (($d['goal_id'] || $d['goal_completion_requested']) && ! $goalCommand) {
                throw new DomainError('GOAL_COMMAND_REQUIRED');
            }
            if ($d['goal_id']) {
                $goal = $this->owned('goals', $user, $d['goal_id']);
                if ($category['system_code'] !== 'goal_expense' || $goal['savings_account_id'] !== $account['id'] || $goal['currency_code'] !== $account['currency_code'] || $goal['cancelled_at'] || ! $goal['savings_account_id']) {
                    throw new DomainError('INVALID_GOAL_LINK');
                }
                if ($old && $old['goal_id'] !== $d['goal_id']) {
                    throw new DomainError('INVALID_GOAL_LINK');
                }
            }
            if (isset($d['currency_code']) && $d['currency_code'] !== $account['currency_code']) {
                throw new DomainError('CURRENCY_MISMATCH');
            }
            $d['currency_code'] = $account['currency_code'];
            $accounts = [$account];
        } else {
            if ($d['account_id'] || $d['category_id'] || $d['goal_id'] || $d['goal_completion_requested'] || ! $d['from_account_id'] || ! $d['to_account_id'] || $d['from_account_id'] === $d['to_account_id']) {
                throw new DomainError('INVALID_OPERATION_SHAPE');
            }
            $from = $this->owned('accounts', $user, $d['from_account_id']);
            $to = $this->owned('accounts', $user, $d['to_account_id']);
            if (isset($d['currency_code']) && $d['currency_code'] !== $from['currency_code']) {
                throw new DomainError('CURRENCY_MISMATCH');
            }
            if ($d['target_currency_code'] && $d['target_currency_code'] !== $to['currency_code']) {
                throw new DomainError('CURRENCY_MISMATCH');
            }
            $d['currency_code'] = $from['currency_code'];
            $d['target_currency_code'] = $to['currency_code'];
            $d['type'] = $from['currency_code'] === $to['currency_code'] ? 'transfer' : 'exchange';
            if (! $old && isset($data['type']) && $data['type'] !== $d['type']) {
                throw new DomainError('OPERATION_TYPE_MISMATCH');
            }
            if ($d['type'] === 'transfer') {
                if ($d['target_amount_minor'] !== null && $d['target_amount_minor'] !== $amount) {
                    throw new DomainError('TRANSFER_AMOUNT_MISMATCH');
                }
                if ($d['quoted_rate'] !== null && Money::rate($d['quoted_rate']) !== '1.000000000000') {
                    throw new DomainError('TRANSFER_AMOUNT_MISMATCH');
                }
                $d['target_amount_minor'] = $amount;
                $d['quoted_rate'] = null;
                $d['effective_rate'] = '1.000000000000';
            } else {
                if ($d['quoted_rate'] !== null) {
                    $d['quoted_rate'] = Money::rate($d['quoted_rate']);
                    $target = Money::target($amount, $d['quoted_rate']);
                    if ($d['target_amount_minor'] !== null && $d['target_amount_minor'] !== $target) {
                        throw new DomainError('FX_AMOUNT_MISMATCH');
                    }
                    $d['target_amount_minor'] = $target;
                }
                $d['target_amount_minor'] = Money::minor($d['target_amount_minor']);
                $d['effective_rate'] = Money::effective($amount, $d['target_amount_minor']);
            }
            $accounts = [$from, $to];
        }
        $fields = ['type', 'occurred_on', 'description', 'account_id', 'from_account_id', 'to_account_id', 'category_id', 'goal_id', 'goal_completion_requested', 'amount_minor', 'currency_code', 'target_amount_minor', 'target_currency_code', 'quoted_rate', 'effective_rate'];
        $normalized = array_intersect_key($d, array_flip($fields));
        $effects = ['type', 'account_id', 'from_account_id', 'to_account_id', 'amount_minor', 'target_amount_minor', 'currency_code', 'target_currency_code'];
        $changed = ! $old || array_intersect_key($normalized, array_flip($effects)) != array_intersect_key($old, array_flip($effects));
        foreach ($accounts as $a) {
            if ($a['archived_at'] && $changed) {
                throw new DomainError('ACCOUNT_ARCHIVED');
            }
        }
        if ($old && $changed) {
            foreach (self::accountIds($old) as $id) {
                if ($id && $this->owned('accounts', $user, $id)['archived_at']) {
                    throw new DomainError('ACCOUNT_ARCHIVED');
                }
            }
        }

        return $normalized;
    }

    public function audit(string $user, string $id, string $action, ?array $before, array $after): void
    {
        DB::table('operation_revisions')->insert(['id' => (string) Str::uuid(), 'user_id' => $user, 'operation_id' => $id, 'actor_id' => $user, 'action' => $action, 'before_payload' => $before ? json_encode(Projection::serialize($before), JSON_THROW_ON_ERROR) : null, 'after_payload' => json_encode(Projection::serialize($after), JSON_THROW_ON_ERROR), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function post(string $user, array $payload, bool $goalCommand = false): array
    {
        $row = $this->normalize($user, $payload, null, $goalCommand);
        $id = (string) Str::uuid();
        DB::table('operations')->insert($row + ['id' => $id, 'user_id' => $user, 'revision' => 1, 'status' => 'posted', 'created_at' => now(), 'updated_at' => now()]);
        $balances = $this->verifyBalances($user, self::accountIds($row));
        $result = $this->owned('operations', $user, $id);
        $this->audit($user, $id, 'created', null, $result);

        return ['operation' => Projection::serialize($result), 'affected_balances' => $balances];
    }

    public function amend(string $user, string $id, array $payload, bool $goalCommand = false): array
    {
        $old = $this->owned('operations', $user, $id);
        Fields::revision($old, $payload);
        if ($old['status'] !== 'posted') {
            throw new DomainError('OPERATION_VOIDED', 409);
        }
        if (DB::table('liability_settlements')->where('user_id', $user)->where('operation_id', $id)->exists()) {
            throw new DomainError('SETTLEMENT_COMMAND_REQUIRED', 409);
        }
        if ($old['goal_id'] && ! $goalCommand) {
            throw new DomainError('GOAL_COMMAND_REQUIRED');
        }
        $old['amount_minor'] = (string) $old['amount_minor'];
        if ($old['target_amount_minor'] !== null) {
            $old['target_amount_minor'] = (string) $old['target_amount_minor'];
        }
        $merged = array_replace($old, $payload); // Effective rate is always recomputed, never editable.
        $merged['currency_code'] = $payload['currency_code'] ?? null;
        $merged['target_currency_code'] = $payload['target_currency_code'] ?? null;
        if (in_array($old['type'], ['transfer', 'exchange'])) {
            $merged['effective_rate'] = null;
        }
        $row = $this->normalize($user, $merged, $old, $goalCommand);
        DB::table('operations')->where('id', $id)->update($row + ['revision' => $old['revision'] + 1, 'updated_at' => now()]);
        $balances = $this->verifyBalances($user, array_merge(self::accountIds($old), self::accountIds($row)));
        $after = $this->owned('operations', $user, $id);
        $this->audit($user, $id, 'amended', $old, $after);

        return ['operation' => Projection::serialize($after), 'affected_balances' => $balances];
    }

    public function void(string $user, string $id, array $payload, bool $settlementCommand = false): array
    {
        $old = $this->owned('operations', $user, $id);
        Fields::revision($old, $payload);
        if (! $settlementCommand && DB::table('liability_settlements')->where('user_id', $user)->where('operation_id', $id)->exists()) {
            throw new DomainError('SETTLEMENT_COMMAND_REQUIRED', 409);
        }
        if ($old['status'] === 'voided') {
            return ['operation' => Projection::serialize($old), 'affected_balances' => []];
        }
        DB::table('operations')->where('id', $id)->update(['status' => 'voided', 'voided_at' => now(), 'revision' => $old['revision'] + 1, 'updated_at' => now()]);
        $balances = $this->verifyBalances($user,self::accountIds($old));
        $after = $this->owned('operations',$user,$id);
        $this->audit($user,$id,'voided',$old,$after);

        return ['operation' => Projection::serialize($after), 'affected_balances' => $balances];
    }
}
