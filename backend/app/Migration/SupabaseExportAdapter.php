<?php

namespace App\Migration;

use App\Domain\DomainError;

final class SupabaseExportAdapter
{
    public function selectOwner(array $source, string $owner): array
    {
        if (($source['format'] ?? null) !== 'norocel.supabase-export') {
            throw new DomainError('INVALID_SERVER_EXPORT');
        }
        $rows = fn ($t) => array_values(array_filter($source[$t] ?? [], fn ($r) => ($r['user_id'] ?? $r['id'] ?? null) === $owner));
        $p = array_values(array_filter($source['profiles'] ?? [], fn ($r) => $r['id'] === $owner))[0] ?? [];

        return ['profile' => $p, 'accounts' => array_map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'type' => $r['type'], 'currencyCode' => $r['currency_code'], 'openingBalance' => $r['opening_balance'], 'includeInTotal' => (bool) $r['include_in_total'], 'createdAt' => $r['created_at']], $rows('accounts')),
            'categories' => array_map(fn ($r) => ['id' => $r['id'], 'key' => $r['key'], 'name' => $r['name'], 'type' => $r['type'], 'createdAt' => $r['created_at']], $rows('categories')),
            'transactions' => array_map(fn ($r) => ['id' => $r['id'], 'amount' => $r['amount'], 'convertedAmount' => $r['converted_amount'], 'exchangeRate' => $r['exchange_rate'], 'desc' => $r['description'] ?? '', 'date' => $r['transaction_date'], 'category' => $r['category'], 'type' => $r['type'], 'accountId' => $r['account_id'], 'fromAccountId' => $r['from_account_id'], 'toAccountId' => $r['to_account_id'], 'currencyCode' => $r['currency_code'], 'convertedCurrencyCode' => $r['converted_currency_code'], 'goalId' => $r['goal_id'], 'createdAt' => $r['created_at']], $rows('transactions')),
            'goals' => array_map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'target' => $r['target_amount'], 'saved' => $r['saved_amount'], 'icon' => $r['icon'], 'deadline' => $r['deadline'], 'currencyCode' => $r['currency_code'], 'savingsAccountId' => $r['savings_account_id'], 'status' => $r['status'], 'completedAt' => $r['completed_at']], $rows('goals')),
            'liabilities' => array_map(fn ($r) => ['id' => $r['id'], 'type' => $r['liability_type'], 'counterpartyName' => $r['counterparty_name'], 'amount' => $r['amount'], 'currencyCode' => $r['currency_code'], 'dueDate' => $r['due_date'], 'comment' => $r['comment'] ?? '', 'status' => $r['status'], 'settlementAccountId' => $r['settlement_account_id'], 'settlementTransactionId' => $r['settlement_transaction_id'], 'createdAt' => $r['created_at']], $rows('liabilities')),
            'budgets' => array_column($rows('budgets'), 'monthly_limit', 'category')];
    }
}
