<?php

namespace App\Domain;

use Brick\Math\BigInteger;
use Illuminate\Support\Facades\Validator;

final class WorkspaceDocument
{
    public const TABLES = ['accounts', 'categories', 'goals', 'operations', 'operation_revisions', 'budget_templates', 'budgets', 'liabilities', 'liability_settlements'];

    public const FIELDS = [
        'accounts' => ['name', 'kind', 'currency_code', 'opening_balance_minor', 'include_in_total', 'archived_at'],
        'categories' => ['kind', 'system_code', 'name', 'is_system', 'archived_at', 'legacy_metadata'],
        'goals' => ['name', 'icon', 'target_amount_minor', 'currency_code', 'deadline', 'savings_account_id', 'cancelled_at', 'legacy_saved_minor', 'legacy_status', 'legacy_completed_at'],
        'operations' => ['type', 'status', 'occurred_on', 'description', 'account_id', 'from_account_id', 'to_account_id', 'amount_minor', 'currency_code', 'target_amount_minor', 'target_currency_code', 'quoted_rate', 'effective_rate', 'category_id', 'goal_id', 'goal_completion_requested', 'voided_at', 'legacy_metadata'],
        'operation_revisions' => ['operation_id', 'action', 'before_payload', 'after_payload'],
        'budget_templates' => ['category_id', 'currency_code', 'limit_minor', 'start_month', 'stop_month'],
        'budgets' => ['category_id', 'currency_code', 'limit_minor', 'period_month', 'disabled', 'source_template_id'],
        'liabilities' => ['kind', 'counterparty_name', 'principal_minor', 'currency_code', 'due_on', 'comment', 'cancelled_at'],
        'liability_settlements' => ['liability_id', 'operation_id'],
    ];

    public function validate(array $d): array
    {
        Fields::check($d, array_fill_keys(array_merge(['schema', 'version', 'exported_at', 'settings'], self::TABLES), 'present'));
        if ($d['schema'] !== 'norocel.workspace' || $d['version'] !== 2) {
            throw new DomainError('INVALID_BACKUP_VERSION');
        }
        Validator::make($d, ['exported_at' => 'required|date', 'settings' => 'required|array'])->validate();
        Fields::check($d['settings'], ['locale' => 'required|in:ro,ru,en', 'base_currency_code' => 'required|in:MDL,EUR,USD', 'theme' => 'required|in:light,dark,system', 'timezone' => 'required|timezone']);
        $maps = [];
        $count = 0;
        $global = [];
        foreach (self::TABLES as $t) {
            if (! is_array($d[$t]) || ! array_is_list($d[$t])) {
                throw new DomainError('INVALID_BACKUP_TABLE', 422, ['table' => $t]);
            }
            $count += count($d[$t]);
            if ($count > 100000 || ($t === 'operations' && count($d[$t]) > 50000)) {
                throw new DomainError('BACKUP_LIMIT_EXCEEDED');
            }
            foreach ($d[$t] as &$row) {
                if (! is_array($row)) {
                    throw new DomainError('INVALID_BACKUP_ROW');
                }
                Fields::check($row, array_fill_keys(array_merge(['id', 'revision', 'created_at', 'updated_at'], self::FIELDS[$t]), 'sometimes'));
                Validator::make($row, ['id' => 'required|uuid', 'revision' => 'required|integer|min:1', 'created_at' => 'required|date', 'updated_at' => 'required|date'])->validate();
                if (isset($global[$row['id']])) {
                    throw new DomainError('DUPLICATE_BACKUP_ID');
                } $global[$row['id']] = true;
                foreach ($row as $key => $value) {
                    if ($value !== null && str_ends_with($key, '_minor')) {
                        Money::minor($value, in_array($key, ['opening_balance_minor', 'legacy_saved_minor']));
                    }
                }
                foreach (['currency_code', 'target_currency_code'] as $key) {
                    if (isset($row[$key])) {
                        Fields::currency($row[$key]);
                    }
                }
                foreach (['occurred_on', 'deadline', 'due_on', 'start_month', 'stop_month', 'period_month'] as $key) {
                    if (isset($row[$key])) {
                        Validator::make($row, [$key => 'date_format:Y-m-d'])->validate();
                        if (str_ends_with($key, 'month') && substr($row[$key], 8) !== '01') {
                            throw new DomainError('INVALID_MONTH');
                        }
                    }
                }
                foreach (['name', 'counterparty_name'] as $key) {
                    if (array_key_exists($key, $row) && $row[$key] !== null) {
                        Validator::make($row, [$key => 'string|max:255'])->validate();
                    }
                }
                foreach (['description', 'comment'] as $key) {
                    if (isset($row[$key])) {
                        Validator::make($row, [$key => 'string|max:2000'])->validate();
                    }
                }
                foreach (['include_in_total', 'is_system', 'disabled', 'goal_completion_requested'] as $key) {
                    if (isset($row[$key]) && ! is_bool($row[$key])) {
                        throw new DomainError('INVALID_BACKUP_BOOLEAN');
                    }
                }
                foreach (['archived_at', 'cancelled_at', 'voided_at', 'legacy_completed_at'] as $key) {
                    if (isset($row[$key])) {
                        Validator::make($row, [$key => 'date'])->validate();
                    }
                }
                $maps[$t][$row['id']] = $row;
            } unset($row);
        }
        $ref = function (string $table, mixed $id) use ($maps): array {
            if (! is_string($id) || ! isset($maps[$table][$id])) {
                throw new DomainError('BROKEN_BACKUP_REFERENCE', 422, ['table' => $table]);
            }

            return $maps[$table][$id];
        };
        $balances = [];
        $codes = [];
        $names = [];
        foreach ($d['accounts'] as $a) {
            Validator::make($a, ['name' => 'required|string|max:255', 'kind' => 'required|in:regular,savings', 'currency_code' => 'required', 'opening_balance_minor' => 'required', 'include_in_total' => 'required'])->validate();
            $balances[$a['id']] = $a['opening_balance_minor'];
        }
        foreach ($d['categories'] as $c) {
            Validator::make($c, ['kind' => 'required|in:income,expense', 'is_system' => 'required|boolean', 'name' => 'nullable|string|max:255'])->validate();
            if ($c['is_system']) {
                $code = $c['system_code'] ?? null;
                if (! isset(WorkspaceService::SYSTEM[$code]) || WorkspaceService::SYSTEM[$code] !== $c['kind'] || isset($codes[$code])) {
                    throw new DomainError('INVALID_SYSTEM_CATEGORY');
                } $codes[$code] = $c['id'];
            } else {
                if (empty($c['name']) || ($c['system_code'] ?? null) !== null || $c['name'] === 'goal_expense') {
                    throw new DomainError('INVALID_CATEGORY_NAME');
                } $key = $c['kind'].':'.mb_strtolower(trim($c['name']));
                if (isset($names[$key])) {
                    throw new DomainError('CATEGORY_NAME_EXISTS');
                } $names[$key] = true;
            }
            if (($c['system_code'] ?? null) === 'goal_expense' && ($c['archived_at'] ?? null)) {
                throw new DomainError('SYSTEM_CATEGORY_PROTECTED');
            }
        }
        if (count($codes) !== count(WorkspaceService::SYSTEM)) {
            throw new DomainError('MISSING_SYSTEM_CATEGORIES');
        }
        $goalAccounts = [];
        foreach ($d['goals'] as $g) {
            Validator::make($g, ['name' => 'required|string|max:255', 'target_amount_minor' => 'required', 'currency_code' => 'required', 'icon' => 'nullable|string|max:32', 'legacy_status' => 'nullable|in:active,reached,spent,cancelled'])->validate();
            if ($g['savings_account_id'] ?? null) {
                $a = $ref('accounts', $g['savings_account_id']);
                if ($a['kind'] !== 'savings' || $a['currency_code'] !== $g['currency_code'] || isset($goalAccounts[$a['id']])) {
                    throw new DomainError('INVALID_GOAL_ACCOUNT');
                } $goalAccounts[$a['id']] = true;
            } elseif (! isset($g['legacy_saved_minor'],$g['legacy_status'])) {
                throw new DomainError('INVALID_LEGACY_GOAL');
            }
        }
        foreach ($d['operations'] as $o) {
            Validator::make($o, ['type' => 'required|in:income,expense,transfer,exchange', 'status' => 'required|in:posted,voided', 'occurred_on' => 'required|date_format:Y-m-d', 'amount_minor' => 'required', 'currency_code' => 'required', 'description' => 'present|string|max:2000', 'goal_completion_requested' => 'required|boolean'])->validate();
            if (in_array($o['type'], ['income', 'expense'])) {
                $a = $ref('accounts', $o['account_id'] ?? null);
                $c = $ref('categories', $o['category_id'] ?? null);
                foreach (['from_account_id', 'to_account_id', 'target_amount_minor', 'target_currency_code', 'quoted_rate', 'effective_rate'] as $k) {
                    if (($o[$k] ?? null) !== null) {
                        throw new DomainError('INVALID_OPERATION_SHAPE');
                    }
                }
                if ($a['currency_code'] !== $o['currency_code'] || $c['kind'] !== $o['type']) {
                    throw new DomainError('CURRENCY_OR_CATEGORY_MISMATCH');
                }
                if ($o['goal_id'] ?? null) {
                    $g = $ref('goals', $o['goal_id']);
                    if ($o['type'] !== 'expense' || ($c['system_code'] ?? null) !== 'goal_expense' || $g['currency_code'] !== $o['currency_code'] || ($g['savings_account_id'] && $g['savings_account_id'] !== $a['id'])) {
                        throw new DomainError('INVALID_GOAL_LINK');
                    }
                } elseif ($o['goal_completion_requested'] || ($c['system_code'] ?? null) === 'goal_expense') {
                    throw new DomainError('INVALID_GOAL_LINK');
                }
                if ($o['status'] === 'posted') {
                    $balances[$a['id']] = Money::sum([$balances[$a['id']], $o['type'] === 'income' ? $o['amount_minor'] : '-'.$o['amount_minor']]);
                }
            } else {
                foreach (['account_id', 'category_id', 'goal_id'] as $k) {
                    if (($o[$k] ?? null) !== null) {
                        throw new DomainError('INVALID_OPERATION_SHAPE');
                    }
                }
                if ($o['goal_completion_requested']) {
                    throw new DomainError('INVALID_OPERATION_SHAPE');
                }
                $a = $ref('accounts', $o['from_account_id'] ?? null);
                $b = $ref('accounts', $o['to_account_id'] ?? null);
                $target = Money::minor($o['target_amount_minor'] ?? null);
                if ($a['id'] === $b['id'] || $a['currency_code'] !== $o['currency_code'] || $b['currency_code'] !== ($o['target_currency_code'] ?? null)) {
                    throw new DomainError('CURRENCY_MISMATCH');
                }
                $type = $a['currency_code'] === $b['currency_code'] ? 'transfer' : 'exchange';
                if ($o['type'] !== $type || ($type === 'transfer' && $o['amount_minor'] !== $target)) {
                    throw new DomainError('INVALID_OPERATION_SHAPE');
                }
                if (Money::rate($o['effective_rate'] ?? '0') !== Money::effective($o['amount_minor'], $target)) {
                    throw new DomainError('FX_AMOUNT_MISMATCH');
                }
                if (isset($o['quoted_rate']) && Money::target($o['amount_minor'], $o['quoted_rate']) !== $target) {
                    throw new DomainError('FX_AMOUNT_MISMATCH');
                }
                if ($o['status'] === 'posted') {
                    $balances[$a['id']] = Money::sum([$balances[$a['id']], '-'.$o['amount_minor']]);
                    $balances[$b['id']] = Money::sum([$balances[$b['id']], $target]);
                }
            }
            if ($o['status'] === 'voided' && ! ($o['voided_at'] ?? null)) {
                throw new DomainError('INVALID_VOID_MARKER');
            }
        }
        $budgetKeys = [];
        $templates = [];
        foreach (['budgets', 'budget_templates'] as $t) {
            foreach ($d[$t] as $b) {
                Validator::make($b, ['category_id' => 'required|uuid', 'currency_code' => 'required', 'limit_minor' => 'required', ($t === 'budgets' ? 'period_month' : 'start_month') => 'required|date_format:Y-m-d'])->validate();
                $c = $ref('categories', $b['category_id']);
                if ($c['kind'] !== 'expense') {
                    throw new DomainError('INVALID_BUDGET_CATEGORY');
                }
                if ($t === 'budgets') {
                    $k = $b['category_id'].':'.$b['period_month'];
                    if (isset($budgetKeys[$k])) {
                        throw new DomainError('BUDGET_EXISTS');
                    } $budgetKeys[$k] = true;
                    if ($b['source_template_id'] ?? null) {
                        $tpl = $ref('budget_templates', $b['source_template_id']);
                        if ($tpl['category_id'] !== $b['category_id']) {
                            throw new DomainError('INVALID_BUDGET_TEMPLATE');
                        }
                    }
                } else {
                    if (isset($b['stop_month']) && $b['stop_month'] < $b['start_month']) {
                        throw new DomainError('INVALID_TEMPLATE_PERIOD');
                    }
                    foreach ($templates[$b['category_id']] ?? [] as $other) {
                        if (($b['stop_month'] ?? null) !== $b['start_month'] && ($other['stop_month'] ?? null) !== $other['start_month'] && $other['start_month'] < ($b['stop_month'] ?? '9999-12-01') && $b['start_month'] < ($other['stop_month'] ?? '9999-12-01')) {
                            throw new DomainError('BUDGET_TEMPLATE_OVERLAP');
                        }
                    } $templates[$b['category_id']][] = $b;
                }
            }
        }
        foreach ($d['liabilities'] as $l) {
            Validator::make($l, ['kind' => 'required|in:receivable,payable,credit', 'principal_minor' => 'required', 'currency_code' => 'required', 'counterparty_name' => 'required|string|max:255'])->validate();
        }
        $settled = [];
        $settledOps = [];
        foreach ($d['liability_settlements'] as $s) {
            $l = $ref('liabilities', $s['liability_id'] ?? null);
            $o = $ref('operations', $s['operation_id'] ?? null);
            if (isset($settled[$l['id']]) || isset($settledOps[$o['id']]) || ($l['cancelled_at'] ?? null) || $o['status'] !== 'posted' || $o['type'] !== ($l['kind'] === 'receivable' ? 'income' : 'expense') || $o['amount_minor'] !== $l['principal_minor'] || $o['currency_code'] !== $l['currency_code'] || ($o['goal_id'] ?? null)) {
                throw new DomainError('INVALID_SETTLEMENT');
            }
            $settled[$l['id']] = true;
            $settledOps[$o['id']] = true;
        }
        foreach ($d['operation_revisions'] as $r) {
            $ref('operations', $r['operation_id'] ?? null);
            Validator::make($r, ['action' => 'required|in:created,amended,voided', 'after_payload' => 'required|array', 'before_payload' => 'nullable|array'])->validate();
            foreach (['before_payload', 'after_payload'] as $key) {
                if ($r[$key] ?? null) {
                    Fields::check($r[$key], array_fill_keys(array_merge(['id', 'revision', 'created_at', 'updated_at'], self::FIELDS['operations']), 'sometimes'));
                    foreach ($r[$key] as $k => $v) {
                        if ($v !== null && str_ends_with($k, '_minor')) {
                            Money::minor($v);
                        }
                    }
                }
            }
        }
        $forecast = [];
        foreach ($balances as $id => $balance) {
            if (BigInteger::of($balance)->isNegative()) {
                throw new DomainError('NEGATIVE_LEGACY_BALANCE', 422, ['account_id' => $id, 'balance_minor' => $balance]);
            } if (BigInteger::of($balance)->isGreaterThan(Money::MAX)) {
                throw new DomainError('BALANCE_OUT_OF_RANGE');
            } $forecast[] = ['account_id' => $id, 'name' => $maps['accounts'][$id]['name'], 'currency_code' => $maps['accounts'][$id]['currency_code'], 'balance_minor' => $balance];
        }

        return ['counts' => array_map(fn ($t) => count($d[$t]), array_combine(self::TABLES, self::TABLES)), 'balances' => $forecast, 'warnings' => [], 'blockers' => []];
    }
}
