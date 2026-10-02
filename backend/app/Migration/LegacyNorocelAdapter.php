<?php

namespace App\Migration;

use App\Domain\DomainError;
use App\Domain\Money;
use App\Domain\WorkspaceDocument;
use App\Domain\WorkspaceService;
use Illuminate\Support\Str;

final class LegacyNorocelAdapter
{
    private const LEGACY_KEYS = ['Зарплата' => 'salary', 'Фриланс' => 'freelance', 'Инвестиции' => 'investments', 'Подарки' => 'gifts', 'Другой доход' => 'other_income', 'Еда' => 'food', 'Транспорт' => 'transport', 'Жильё' => 'housing', 'Развлечения' => 'entertainment', 'Одежда' => 'shopping', 'Здоровье' => 'health', 'Образование' => 'education', 'Связь' => 'utilities', 'Другое' => 'other_expense', 'Salary' => 'salary', 'Investments' => 'investments', 'Gifts' => 'gifts', 'Other income' => 'other_income', 'Food' => 'food', 'Housing' => 'housing', 'Entertainment' => 'entertainment', 'Shopping' => 'shopping', 'Health' => 'health', 'Education' => 'education', 'Utilities' => 'utilities', 'Other expense' => 'other_expense', 'Salariu' => 'salary', 'Freelance' => 'freelance', 'Investitii' => 'investments', 'Cadouri' => 'gifts', 'Alt venit' => 'other_income', 'Mancare' => 'food', 'Transport' => 'transport', 'Locuinta' => 'housing', 'Divertisment' => 'entertainment', 'Cumparaturi' => 'shopping', 'Sanatate' => 'health', 'Educatie' => 'education', 'Utilitati' => 'utilities', 'Alte cheltuieli' => 'other_expense'];

    public function convert(array $old, ?string $month): array
    {
        if (! isset($old['accounts'],$old['transactions'],$old['categories'],$old['goals'],$old['liabilities'],$old['budgets'])) {
            throw new DomainError('UNKNOWN_LEGACY_FORMAT');
        }
        $warnings = [];
        $profile = $old['profile'] ?? [];
        foreach (['email', 'billing', 'isAdmin', 'is_admin', 'billing_plan', 'id', 'password', 'avatarUrl', 'avatar_url', 'fullName', 'full_name'] as $field) {
            if (array_key_exists($field, $profile)) {
                $warnings[] = ['code' => 'LEGACY_PROFILE_FIELD_IGNORED', 'field' => $field];
            }
        }
        if ($old['budgets'] && ! isset($profile['currency'])) {
            throw new DomainError('LEGACY_BUDGET_CURRENCY_REQUIRED');
        }
        $currency = $profile['currency'] ?? 'MDL';
        $d = ['schema' => 'norocel.workspace', 'version' => 2, 'exported_at' => now()->toIso8601String(), 'settings' => ['locale' => $profile['language'] ?? 'ro', 'base_currency_code' => $currency, 'theme' => 'system', 'timezone' => 'Europe/Chisinau']];
        foreach (WorkspaceDocument::TABLES as $table) {
            $d[$table] = [];
        }
        $stamp = fn ($r) => ['id' => $r['id'] ?? (string) Str::uuid(), 'revision' => 1, 'created_at' => $r['createdAt'] ?? now()->toIso8601String(), 'updated_at' => now()->toIso8601String()];
        $cats = [];
        foreach (WorkspaceService::SYSTEM as $code => $kind) {
            $c = $stamp([]) + ['kind' => $kind, 'system_code' => $code, 'name' => null, 'is_system' => true, 'archived_at' => null, 'legacy_metadata' => null];
            $d['categories'][] = $c;
            $cats[$kind.':'.$code] = $c['id'];
        }
        foreach ($old['categories'] as $r) {
            $c = $stamp($r) + ['kind' => $r['type'], 'system_code' => null, 'name' => $r['name'], 'is_system' => false, 'archived_at' => null, 'legacy_metadata' => ['category_key' => $r['key']]];
            $d['categories'][] = $c;
            $cats[$r['type'].':'.$r['key']] = $c['id'];
        }
        $category = function (string $kind, string $key) use (&$cats, &$d, &$warnings, $stamp): string {
            if(isset($cats[$kind.':'.$key]))return $cats[$kind.':'.$key];
            $key = self::LEGACY_KEYS[$key] ?? $key;
            if (isset($cats[$kind.':'.$key])) {
                return $cats[$kind.':'.$key];
            }
            $c = $stamp([]) + ['kind' => $kind, 'system_code' => null, 'name' => 'Migrated: '.$key, 'is_system' => false, 'archived_at' => now()->toIso8601String(), 'legacy_metadata' => ['category_key' => $key]];
            $d['categories'][] = $c;
            $cats[$kind.':'.$key] = $c['id'];
            $warnings[] = ['code' => 'ARCHIVED_CATEGORY_PLACEHOLDER', 'kind' => $kind, 'key' => $key];

            return $c['id'];
        };
        $accounts = [];
        $oldBalances = [];
        foreach ($old['accounts'] as $r) {
            $a = $stamp($r) + ['name' => $r['name'], 'kind' => $r['type'], 'currency_code' => $r['currencyCode'], 'opening_balance_minor' => Money::decimal((string) ($r['openingBalance'] ?? '0')), 'include_in_total' => $r['includeInTotal'] ?? true, 'archived_at' => null];
            $d['accounts'][] = $a;
            $accounts[$a['id']] = $a;
            $oldBalances[$a['id']] = $a['opening_balance_minor'];
        }
        foreach ($old['goals'] as $r) {
            $account = $r['savingsAccountId'] ?? null;
            $status = $r['status'] ?? 'active';
            $g = $stamp($r) + ['name' => $r['name'], 'icon' => $r['icon'] ?? '🎯', 'target_amount_minor' => Money::decimal((string) $r['target']), 'currency_code' => $r['currencyCode'], 'deadline' => ($r['deadline'] ?? null) ?: null, 'savings_account_id' => $account ?: null, 'cancelled_at' => $status === 'cancelled' ? ($r['completedAt'] ?? now()->toIso8601String()) : null, 'legacy_saved_minor' => $account ? null : Money::decimal((string) ($r['saved'] ?? '0')), 'legacy_status' => ! $account || $status === 'spent' ? $status : null, 'legacy_completed_at' => ($r['completedAt'] ?? null) ?: null];
            if (! $account || $status === 'spent') {
                $warnings[] = ['code' => 'LEGACY_GOAL_STATE', 'goal_id' => $g['id'], 'status' => $status, 'unlinked' => ! $account];
            }
            $d['goals'][] = $g;
        }
        $operations = [];
        foreach ($old['transactions'] as $r) {
            $type = $r['type'];
            $o = $stamp($r) + ['type' => $type, 'status' => 'posted', 'occurred_on' => $r['date'], 'description' => $r['desc'] ?? '', 'amount_minor' => Money::decimal((string) $r['amount']), 'currency_code' => $r['currencyCode'], 'account_id' => null, 'from_account_id' => null, 'to_account_id' => null, 'target_amount_minor' => null, 'target_currency_code' => null, 'category_id' => null, 'goal_id' => null, 'goal_completion_requested' => false, 'quoted_rate' => null, 'effective_rate' => null, 'voided_at' => null, 'legacy_metadata' => null];
            if (in_array($type, ['income', 'expense'])) {
                $o['account_id'] = $r['accountId'] ?? null;
                $o['goal_id'] = ($r['goalId'] ?? null) ?: null;
                $o['category_id'] = $category($type, $r['category'] ?? '');
                if (! isset($accounts[$o['account_id']])) {
                    throw new DomainError('MISSING_LEGACY_ACCOUNT');
                }
                $oldBalances[$o['account_id']] = Money::sum([$oldBalances[$o['account_id']], $type === 'income' ? $o['amount_minor'] : '-'.$o['amount_minor']]);
            } else {
                $from = $r['fromAccountId'] ?? null;
                $to = $r['toAccountId'] ?? null;
                if (! isset($accounts[$from],$accounts[$to])) {
                    throw new DomainError('MISSING_LEGACY_ACCOUNT');
                }
                $o['from_account_id'] = $from;
                $o['to_account_id'] = $to;
                $o['target_amount_minor'] = Money::decimal((string) $r['convertedAmount']);
                $o['target_currency_code'] = $r['convertedCurrencyCode'];
                $o['type'] = $accounts[$from]['currency_code'] === $accounts[$to]['currency_code'] ? 'transfer' : 'exchange';
                $o['effective_rate'] = Money::effective($o['amount_minor'], $o['target_amount_minor']);
                $legacyRate = isset($r['exchangeRate']) ? (string) $r['exchangeRate'] : null;
                if ($o['type'] !== $type || ($legacyRate && Money::rate($legacyRate) !== $o['effective_rate'])) {
                    $o['legacy_metadata'] = ['original_type' => $type, 'original_rate' => $legacyRate];
                    $warnings[] = ['code' => 'FX_METADATA_NORMALIZED', 'operation_id' => $o['id'], 'original_type' => $type, 'original_rate' => $legacyRate, 'effective_rate' => $o['effective_rate']];
                }
                $oldBalances[$from] = Money::sum([$oldBalances[$from], '-'.$o['amount_minor']]);
                $oldBalances[$to] = Money::sum([$oldBalances[$to], $o['target_amount_minor']]);
            }
            $d['operations'][] = $o;
            $operations[$o['id']] = $o;
        }
        foreach ($old['liabilities'] as $r) {
            $l = $stamp($r) + ['kind' => $r['type'], 'counterparty_name' => $r['counterpartyName'], 'principal_minor' => Money::decimal((string) $r['amount']), 'currency_code' => $r['currencyCode'], 'due_on' => ($r['dueDate'] ?? null) ?: null, 'comment' => $r['comment'] ?? '', 'cancelled_at' => null];
            $d['liabilities'][] = $l;
            if (($r['status'] ?? 'open') === 'settled') {
                $id = $r['settlementTransactionId'] ?? null;
                $op = $operations[$id] ?? null;
                if (! $op || $op['account_id'] !== ($r['settlementAccountId'] ?? null) || $op['amount_minor'] !== $l['principal_minor'] || $op['currency_code'] !== $l['currency_code'] || $op['type'] !== ($l['kind'] === 'receivable' ? 'income' : 'expense') || $op['goal_id']) {
                    throw new DomainError('UNPROVEN_LEGACY_SETTLEMENT', 422, ['liability_id' => $l['id']]);
                }
                $d['liability_settlements'][] = $stamp([]) + ['liability_id' => $l['id'], 'operation_id' => $id];
            }
        }
        if ($old['budgets']) {
            if (! $month || ! preg_match('/^\d{4}-\d{2}$/D', $month)) {
                throw new DomainError('LEGACY_BUDGET_MONTH_REQUIRED');
            }
            foreach ($old['budgets'] as $key => $limit) {
                $cid = $category('expense', $key);
                $tpl = $stamp([]) + ['category_id' => $cid, 'currency_code' => $currency, 'limit_minor' => Money::decimal((string) $limit), 'start_month' => $month.'-01', 'stop_month' => null];
                $d['budget_templates'][] = $tpl;
                $d['budgets'][] = $stamp([]) + ['category_id' => $cid, 'currency_code' => $currency, 'limit_minor' => $tpl['limit_minor'], 'period_month' => $month.'-01', 'disabled' => false, 'source_template_id' => $tpl['id']];
            }
            $warnings[] = ['code' => 'LEGACY_BUDGET_ASSUMPTION', 'month' => $month, 'currency_code' => $currency];
        }
        $manifest = app(WorkspaceDocument::class)->validate($d);
        $reconciliation = [];
        foreach ($manifest['balances'] as $b) {
            $reconciliation[] = $b + ['source_balance_minor' => $oldBalances[$b['account_id']], 'delta_minor' => Money::sum([$b['balance_minor'], '-'.$oldBalances[$b['account_id']]])];
        }

        return ['document' => $d, 'warnings' => $warnings, 'reconciliation' => $reconciliation];
    }
}
