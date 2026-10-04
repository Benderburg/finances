<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WorkspaceService
{
    public const SYSTEM = ['salary' => 'income', 'freelance' => 'income', 'investments' => 'income', 'gifts' => 'income', 'other_income' => 'income', 'food' => 'expense', 'transport' => 'expense', 'housing' => 'expense', 'entertainment' => 'expense', 'shopping' => 'expense', 'health' => 'expense', 'education' => 'expense', 'utilities' => 'expense', 'other_expense' => 'expense', 'goal_expense' => 'expense'];

    public function __construct(private readonly FinancialEngine $engine, private readonly Projection $projection) {}

    public function initialize(string $user): void
    {
        DB::transaction(function () use ($user) {
            $new = DB::table('user_settings')->insertOrIgnore(['user_id' => $user]);
            $changed = false;
            DB::table('user_settings')->where('user_id', $user)->lockForUpdate()->first();
            foreach (self::SYSTEM as $code => $kind) {
                if (! DB::table('categories')->where('user_id', $user)->where('system_code', $code)->exists()) {
                    $this->insert('categories', $user, ['kind' => $kind, 'system_code' => $code, 'name' => null, 'is_system' => true]);
                    $changed = true;
                }
            }
            if (! DB::table('accounts')->where('user_id', $user)->exists()) {
                $this->insert('accounts', $user, ['name' => 'Main', 'kind' => 'regular', 'currency_code' => 'MDL', 'opening_balance_minor' => '0', 'include_in_total' => true]);
                $changed = true;
            }
            if (! $new && $changed) {
                DB::table('user_settings')->where('user_id', $user)->increment('workspace_revision');
            }
        });
    }

    public function insert(string $table, string $user, array $data): array
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert($data + ['id' => $id, 'user_id' => $user, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return (array) DB::table($table)->where('user_id', $user)->where('id', $id)->first();
    }

    public function update(string $table, string $user, array $old, array $data): array
    {
        DB::table($table)->where('user_id', $user)->where('id', $old['id'])->update($data + ['revision' => $old['revision'] + 1, 'updated_at' => now()]);

        return $this->engine->owned($table, $user, $old['id']);
    }

    public static function rules(string $table, bool $create): array
    {
        $r = $create ? 'required' : 'sometimes';
        $money = $r.'|string|regex:/^[0-9]{1,15}$/';
        $currency = $r.'|in:MDL,EUR,USD,RON';
        $rules = match ($table) {
            'accounts' => ['name' => $r.'|string|max:255', 'kind' => $r.'|in:regular,savings', 'currency_code' => $currency, 'opening_balance_minor' => $money, 'include_in_total' => 'sometimes|boolean'],
            'categories' => ['name' => $r.'|string|max:255', 'kind' => $r.'|in:income,expense'],
            'goals' => ['name' => $r.'|string|max:255', 'target_amount_minor' => $money, 'currency_code' => $currency, 'savings_account_id' => 'nullable|uuid', 'new_account_name' => 'sometimes|string|max:255', 'deadline' => 'nullable|date_format:Y-m-d', 'icon' => 'nullable|string|max:32'],
            'liabilities' => ['kind' => $r.'|in:receivable,payable,credit', 'counterparty_name' => $r.'|string|max:255', 'principal_minor' => $money, 'currency_code' => $currency, 'due_on' => 'nullable|date_format:Y-m-d', 'comment' => 'sometimes|string|max:2000'],
            'budgets' => ['category_id' => $r.'|uuid', 'period_month' => $r.'|date_format:Y-m-d', 'currency_code' => $currency, 'limit_minor' => $money],
            'budget_templates' => ['category_id' => $r.'|uuid', 'start_month' => $r.'|date_format:Y-m-d', 'stop_month' => 'nullable|date_format:Y-m-d', 'currency_code' => $currency, 'limit_minor' => $money],
            'operations' => ['type' => $r.'|in:income,expense,transfer,exchange', 'occurred_on' => $r.'|date_format:Y-m-d', 'description' => 'sometimes|string|max:2000', 'account_id' => 'nullable|uuid', 'from_account_id' => 'nullable|uuid', 'to_account_id' => 'nullable|uuid', 'category_id' => 'nullable|uuid', 'amount_minor' => $money, 'currency_code' => 'sometimes|in:MDL,EUR,USD,RON', 'target_amount_minor' => 'nullable|string|regex:/^[1-9][0-9]{0,14}$/', 'target_currency_code' => 'nullable|in:MDL,EUR,USD,RON', 'quoted_rate' => 'nullable|string|max:64'],
            default => [],
        };
        if (! $create) {
            $rules['expected_revision'] = 'required|integer|min:1';
        }

        return $rules;
    }

    public function operation(string $user, string $action, ?string $id, array $p): array
    {
        if ($action === 'void') {
            Fields::check($p, ['expected_revision' => 'required|integer|min:1']);

            return $this->engine->void($user, $id, $p);
        }
        Fields::check($p, self::rules('operations', $action === 'create'));

        return $action === 'create' ? $this->engine->post($user, $p) : $this->engine->amend($user, $id, $p);
    }

    public function resource(string $user, string $table, string $action, ?string $id, array $p): array
    {
        $old = $id ? $this->engine->owned($table, $user, $id) : null;
        if ($old) {
            Fields::revision($old, $p);
        }
        if (in_array($action, ['archive', 'unarchive', 'cancel', 'resume', 'delete', 'stop'])) {
            Fields::check($p, ['expected_revision' => 'required|integer|min:1'] + ($action === 'stop' ? ['stop_month' => 'required|date_format:Y-m-d'] : []));

            return $this->lifecycle($user, $table, $action, $old, $p);
        }
        Fields::check($p, self::rules($table, $action === 'create'));
        unset($p['expected_revision']);
        foreach (['opening_balance_minor', 'target_amount_minor', 'limit_minor', 'principal_minor'] as $field) {
            if (isset($p[$field])) {
                $p[$field] = Money::minor($p[$field], $field === 'opening_balance_minor');
            }
        }
        if ($table === 'accounts') {
            if ($old && (array_key_exists('currency_code', $p) || array_key_exists('opening_balance_minor', $p))) {
                throw new DomainError('ACCOUNT_FIELDS_IMMUTABLE');
            }
            if ($old && isset($p['kind']) && $p['kind'] !== $old['kind'] && DB::table('goals')->where('user_id', $user)->where('savings_account_id', $id)->exists()) {
                throw new DomainError('GOAL_ACCOUNT_KIND_IMMUTABLE');
            }
        }
        if ($table === 'categories') {
            if ($old && $old['system_code'] === 'goal_expense') {
                throw new DomainError('SYSTEM_CATEGORY_PROTECTED');
            }
            if (isset($p['name'])) {
                $p['name'] = trim($p['name']);
                if (! $p['name'] || $p['name'] === 'goal_expense') {
                    throw new DomainError('INVALID_CATEGORY_NAME');
                } $p['name_key'] = mb_strtolower($p['name']);
            }
            if ($old && isset($p['kind']) && $p['kind'] !== $old['kind'] && ($old['is_system'] || $this->categoryUsed($user, $id))) {
                throw new DomainError('CATEGORY_TYPE_IMMUTABLE');
            }
            if (isset($p['name_key']) && DB::table('categories')->where('user_id', $user)->where('kind', $p['kind'] ?? $old['kind'])->where('name_key', $p['name_key'])->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
                throw new DomainError('CATEGORY_NAME_EXISTS', 409);
            }
        }
        if ($table === 'goals') {
            if ($old && ! $old['savings_account_id']) {
                throw new DomainError('LEGACY_GOAL_READ_ONLY');
            }
            if ($old && (array_key_exists('currency_code', $p) || array_key_exists('savings_account_id', $p) || array_key_exists('new_account_name', $p))) {
                throw new DomainError('GOAL_LINK_IMMUTABLE');
            }
            if (! $old) {
                $account = isset($p['savings_account_id']) ? $this->engine->owned('accounts', $user, $p['savings_account_id']) : $this->insert('accounts', $user, ['name' => $p['new_account_name'] ?? $p['name'], 'kind' => 'savings', 'currency_code' => $p['currency_code'], 'opening_balance_minor' => '0', 'include_in_total' => true]);
                if ($account['kind'] !== 'savings' || $account['currency_code'] !== $p['currency_code'] || $account['archived_at'] || DB::table('goals')->where('user_id', $user)->where('savings_account_id', $account['id'])->exists()) {
                    throw new DomainError('INVALID_GOAL_ACCOUNT');
                }
                $p['savings_account_id'] = $account['id'];
                unset($p['new_account_name']);
            }
        }
        if ($table === 'liabilities' && $old && DB::table('liability_settlements')->where('user_id', $user)->where('liability_id', $id)->exists()) {
            foreach (['kind', 'principal_minor', 'currency_code'] as $field) {
                if (array_key_exists($field, $p)) {
                    throw new DomainError('SETTLED_LIABILITY_IMMUTABLE');
                }
            }
        }
        if (in_array($table, ['budgets', 'budget_templates'])) {
            $merged = array_replace($old ?? [], $p);
            $category = $this->engine->owned('categories', $user, $merged['category_id']);
            if ($category['kind'] !== 'expense' || $category['archived_at']) {
                throw new DomainError('INVALID_BUDGET_CATEGORY');
            }
            foreach (['period_month', 'start_month', 'stop_month'] as $field) {
                if (isset($merged[$field]) && substr($merged[$field], 8) !== '01') {
                    throw new DomainError('INVALID_MONTH');
                }
            }
            if ($old && (isset($p['category_id']) || ($table === 'budgets' && isset($p['period_month'])))) {
                throw new DomainError('BUDGET_IDENTITY_IMMUTABLE');
            }
            if ($table === 'budget_templates') {
                if (isset($merged['stop_month']) && $merged['stop_month'] < $merged['start_month']) {
                    throw new DomainError('INVALID_TEMPLATE_PERIOD');
                }
                $overlap = DB::table('budget_templates')->where('user_id', $user)->where('category_id', $merged['category_id'])->when($id, fn ($q) => $q->where('id', '<>', $id))->where(fn ($q) => $q->whereNull('stop_month')->orWhereColumn('stop_month', '>', 'start_month'))->where(fn ($q) => $q->whereNull('stop_month')->orWhere('stop_month', '>', $merged['start_month']));
                if ($merged['stop_month'] ?? null) {
                    $overlap->where('start_month', '<', $merged['stop_month']);
                }
                if (($merged['stop_month'] ?? null) !== $merged['start_month'] && $overlap->exists()) {
                    throw new DomainError('BUDGET_TEMPLATE_OVERLAP', 409);
                }
            } elseif (! $old && DB::table('budgets')->where('user_id', $user)->where('category_id', $p['category_id'])->where('period_month', $p['period_month'])->exists()) {
                throw new DomainError('BUDGET_EXISTS', 409);
            }
        }
        $row = $old ? $this->update($table, $user, $old, $p) : $this->insert($table, $user, $p);

        return $this->project($user, $table, $row);
    }

    public function project(string $user, string $table, array $row): array
    {
        return match ($table) {
            'accounts' => $this->projection->account($user, $row),'goals' => $this->projection->goal($user, $row),'budgets' => $this->projection->budget($user, $row),'liabilities' => $this->projection->liability($user, $row),default => Projection::serialize($row)
        };
    }

    private function categoryUsed(string $user, string $id): bool
    {
        foreach (['operations', 'budgets', 'budget_templates'] as $t) {
            if (DB::table($t)->where('user_id', $user)->where('category_id', $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function lifecycle(string $user, string $table, string $action, array $old, array $p): array
    {
        $id = $old['id'];
        if ($table === 'categories' && ($old['system_code'] === 'goal_expense' || ($action === 'delete' && $old['is_system']))) {
            throw new DomainError('SYSTEM_CATEGORY_PROTECTED');
        }
        if ($table === 'goals' && ! $old['savings_account_id']) {
            throw new DomainError('LEGACY_GOAL_READ_ONLY');
        }
        if ($table === 'liabilities' && DB::table('liability_settlements')->where('user_id', $user)->where('liability_id', $id)->exists()) {
            throw new DomainError('SETTLED_LIABILITY_IMMUTABLE');
        }
        if ($action === 'delete') {
            if ($table === 'budgets') {
                return $this->project($user, $table, $this->update($table, $user, $old, ['disabled' => true]));
            }
            $used = match ($table) {
                'categories' => $this->categoryUsed($user, $id),
                'accounts' => (string) $old['opening_balance_minor'] !== '0' || DB::table('operations')->where('user_id', $user)->where(fn ($q) => $q->where('account_id', $id)->orWhere('from_account_id', $id)->orWhere('to_account_id', $id))->exists() || DB::table('goals')->where('user_id', $user)->where('savings_account_id', $id)->exists(),
                'goals' => DB::table('operations')->where('user_id', $user)->where(fn ($q) => $q->where('goal_id', $id)->orWhere('account_id', $old['savings_account_id'])->orWhere('from_account_id', $old['savings_account_id'])->orWhere('to_account_id', $old['savings_account_id']))->exists() || $this->engine->balance($user, $old['savings_account_id']) !== '0',
                default => true,
            };
            if ($used) {
                throw new DomainError('ENTITY_IN_USE', 409);
            }
            DB::table($table)->where('user_id', $user)->where('id', $id)->delete();

            return ['deleted' => true];
        }
        if ($table === 'budget_templates') {
            if (substr($p['stop_month'], 8) !== '01' || $p['stop_month'] < $old['start_month']) {
                throw new DomainError('INVALID_TEMPLATE_PERIOD');
            }

            return Projection::serialize($this->update($table, $user, $old, ['stop_month' => $p['stop_month']]));
        }
        $field = in_array($table, ['accounts', 'categories']) ? 'archived_at' : 'cancelled_at';
        $row = $this->update($table, $user, $old, [$field => in_array($action, ['archive', 'cancel']) ? now() : null]);
        if ($table === 'categories' && $action === 'archive') {
            $timezone = DB::table('user_settings')->where('user_id', $user)->value('timezone');
            $next = now($timezone)->startOfMonth()->addMonth()->toDateString();
            foreach (DB::table('budget_templates')->where('user_id', $user)->where('category_id', $id)->where(fn ($q) => $q->whereNull('stop_month')->orWhere('stop_month', '>', $next))->get() as $template) {
                $this->update('budget_templates', $user, (array) $template, ['stop_month' => max($next, $template->start_month)]);
            }
        }

        return $this->project($user, $table, $row);
    }

    public function spend(string $user, string $id, array $p, ?string $operation = null): array
    {
        $goal = $this->engine->owned('goals', $user, $id);
        Fields::revision($goal, $p);
        Fields::check($p, ['expected_revision' => 'required|integer|min:1', 'expected_operation_revision' => 'sometimes|integer|min:1', 'amount_minor' => 'required|string', 'occurred_on' => 'required|date_format:Y-m-d', 'description' => 'sometimes|string|max:2000', 'goal_completion_requested' => 'sometimes|boolean']);
        if (! $goal['savings_account_id'] || $goal['cancelled_at']) {
            throw new DomainError('GOAL_UNAVAILABLE');
        }
        $category = DB::table('categories')->where('user_id', $user)->where('system_code', 'goal_expense')->value('id');
        $payload = ['type' => 'expense', 'account_id' => $goal['savings_account_id'], 'category_id' => $category, 'goal_id' => $id, 'amount_minor' => $p['amount_minor'], 'occurred_on' => $p['occurred_on'], 'description' => $p['description'] ?? '', 'goal_completion_requested' => $p['goal_completion_requested'] ?? false];
        if ($operation) {
            $op = $this->engine->owned('operations', $user, $operation);
            if ($op['goal_id'] !== $id) {
                throw new DomainError('NOT_FOUND', 404);
            }
            $payload['expected_revision'] = $p['expected_operation_revision'] ?? null;
            $result = $this->engine->amend($user, $operation, $payload, true);
        } else {
            $result = $this->engine->post($user, $payload, true);
        }
        $goal = $this->update('goals', $user, $goal, []);
        $result['goal'] = $this->projection->goal($user, $goal);

        return $result;
    }

    public function settle(string $user, string $id, array $p, bool $void = false): array
    {
        $l = $this->engine->owned('liabilities', $user, $id);
        Fields::revision($l, $p);
        $link = DB::table('liability_settlements')->where('user_id', $user)->where('liability_id', $id)->first();
        if ($void) {
            Fields::check($p, ['expected_revision' => 'required|integer|min:1']);
            if (! $link) {
                throw new DomainError('NOT_SETTLED', 409);
            }
            $op = $this->engine->owned('operations', $user, $link->operation_id);
            $result = $this->engine->void($user, $op['id'], ['expected_revision' => $op['revision']], true);
            DB::table('liability_settlements')->where('id', $link->id)->delete();
        } else {
            Fields::check($p, ['expected_revision' => 'required|integer|min:1', 'account_id' => 'required|uuid', 'category_id' => 'required|uuid', 'occurred_on' => 'required|date_format:Y-m-d', 'description' => 'sometimes|string|max:2000']);
            if ($link || $l['cancelled_at']) {
                throw new DomainError('LIABILITY_UNAVAILABLE', 409);
            }
            $account = $this->engine->owned('accounts', $user, $p['account_id']);
            if ($account['currency_code'] !== $l['currency_code']) {
                throw new DomainError('CURRENCY_MISMATCH');
            }
            $result = $this->engine->post($user, ['type' => $l['kind'] === 'receivable' ? 'income' : 'expense', 'account_id' => $p['account_id'], 'category_id' => $p['category_id'], 'amount_minor' => (string) $l['principal_minor'], 'occurred_on' => $p['occurred_on'], 'description' => $p['description'] ?? $l['counterparty_name']]);
            $this->insert('liability_settlements', $user, ['liability_id' => $id, 'operation_id' => $result['operation']['id']]);
        }
        $l = $this->update('liabilities', $user, $l, []);
        $result['liability'] = $this->projection->liability($user, $l);

        return $result;
    }

    public function ensureMonth(string $user, array $p): array
    {
        Fields::check($p, ['period_month' => 'required|date_format:Y-m-d']);
        $month = $p['period_month'];
        if (substr($month, 8) !== '01') {
            throw new DomainError('INVALID_MONTH');
        }
        $created = [];
        foreach (DB::table('budget_templates')->where('user_id', $user)->where('start_month', '<=', $month)->where(fn ($q) => $q->whereNull('stop_month')->orWhere('stop_month', '>', $month))->get() as $t) {
            if ($this->engine->owned('categories', $user, $t->category_id)['archived_at']) {
                continue;
            }
            if (! DB::table('budgets')->where('user_id', $user)->where('category_id', $t->category_id)->where('period_month', $month)->exists()) {
                $created[] = $this->insert('budgets', $user, ['category_id' => $t->category_id, 'period_month' => $month, 'currency_code' => $t->currency_code, 'limit_minor' => (string) $t->limit_minor, 'source_template_id' => $t->id]);
            }
        }

        return ['created' => count($created)];
    }
}
