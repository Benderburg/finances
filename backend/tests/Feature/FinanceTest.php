<?php

namespace Tests\Feature;

use App\Domain\BackupService;
use App\Domain\DomainError;
use App\Domain\FinancialEngine;
use App\Domain\WorkspaceService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use DatabaseTruncation;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['full_name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password-long-123']);
        $this->owner->forceFill(['email_verified_at' => now()])->save();
        app(WorkspaceService::class)->initialize($this->owner->id);
        $this->actingAs($this->owner);
    }

    private function command(string $method, string $url, array $p = [], ?string $key = null)
    {
        return $this->json($method, '/api/v1/'.$url, $p, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function account(string $opening = '100000', string $currency = 'MDL', string $kind = 'regular'): array
    {
        return $this->command('POST', 'accounts', ['name' => 'Account', 'kind' => $kind, 'currency_code' => $currency, 'opening_balance_minor' => $opening])->assertOk()->json('data');
    }

    private function category(string $code = 'food'): string
    {
        return DB::table('categories')->where('user_id', $this->owner->id)->where('system_code', $code)->value('id');
    }

    private function recordOperation(array $p): array
    {
        return $this->command('POST', 'operations', $p + ['occurred_on' => '2026-10-02'])->assertOk()->json('data.operation');
    }

    private function income(array $a, string $amount): array
    {
        return $this->recordOperation(['type' => 'income', 'account_id' => $a['id'], 'category_id' => $this->category('salary'), 'amount_minor' => $amount]);
    }

    private function expense(array $a, string $amount): array
    {
        return $this->recordOperation(['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $this->category(), 'amount_minor' => $amount]);
    }

    private function balance(array $a): string
    {
        return app(FinancialEngine::class)->balance($this->owner->id, $a['id']);
    }

    public function test_opening_income_expense_and_flow(): void
    {
        $a = $this->account();
        $this->income($a, '50000');
        $this->expense($a, '20000');
        $this->assertSame('130000', $this->balance($a));
        $this->getJson('/api/v1/dashboard?month=2026-10')->assertOk()->assertJsonPath('data.cash_flow.MDL.income_minor', '50000')->assertJsonPath('data.cash_flow.MDL.expense_minor', '20000');
    }

    public function test_transfer_exchange_rounding_and_cash_flow(): void
    {
        $a = $this->account('300000');
        $b = $this->account('0');
        $this->recordOperation(['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $b['id'], 'amount_minor' => '100000']);
        $u = $this->account('0', 'USD');
        $op = $this->recordOperation(['type' => 'exchange', 'from_account_id' => $a['id'], 'to_account_id' => $u['id'], 'amount_minor' => '180000', 'target_amount_minor' => '10000']);
        $this->assertSame('18.000000000000', $op['effective_rate']);
        $this->assertSame('20000', $this->balance($a));
        $this->assertSame('10000', $this->balance($u));
        $op = $this->recordOperation(['type' => 'exchange', 'from_account_id' => $a['id'], 'to_account_id' => $u['id'], 'amount_minor' => '10000', 'quoted_rate' => '3']);
        $this->assertSame('3333', $op['target_amount_minor']);
        $this->command('POST', 'operations', ['type' => 'exchange', 'from_account_id' => $a['id'], 'to_account_id' => $u['id'], 'amount_minor' => '1000', 'target_amount_minor' => '999', 'quoted_rate' => '3', 'occurred_on' => '2026-10-02'])->assertStatus(422)->assertJsonPath('error.code', 'FX_AMOUNT_MISMATCH');
        $this->getJson('/api/v1/dashboard?month=2026-10')->assertJsonPath('data.cash_flow.MDL.income_minor', '0');
    }

    public function test_edit_void_check_both_accounts_and_revision(): void
    {
        $a = $this->account('100000');
        $b = $this->account('0');
        $o = $this->recordOperation(['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $b['id'], 'amount_minor' => '50000']);
        $this->expense($b, '45000');
        $this->command('POST', 'operations/'.$o['id'].'/void', ['expected_revision' => 1])->assertStatus(409)->assertJsonPath('error.code', 'INSUFFICIENT_FUNDS');
        $this->command('PATCH', 'operations/'.$o['id'], ['expected_revision' => 1, 'amount_minor' => '40000', 'target_amount_minor' => '40000'])->assertStatus(409);
        $this->command('PATCH', 'operations/'.$o['id'], ['expected_revision' => 1, 'description' => 'Updated'])->assertOk();
        $this->command('PATCH', 'operations/'.$o['id'], ['expected_revision' => 1, 'description' => 'Stale'])->assertStatus(409)->assertJsonPath('error.code', 'STALE_REVISION');
        $this->assertSame('5000', $this->balance($b));
        $a = $this->account('0');
        $i = $this->income($a, '100000');
        $this->expense($a, '90000');
        $this->command('POST', 'operations/'.$i['id'].'/void', ['expected_revision' => 1])->assertStatus(409);
    }

    public function test_retry_conflict_and_archive_currency(): void
    {
        $key = (string) Str::uuid();
        $p = ['name' => 'One', 'kind' => 'regular', 'currency_code' => 'MDL', 'opening_balance_minor' => '1000'];
        $a = $this->command('POST', 'accounts', $p, $key)->assertOk()->json('data');
        $this->command('POST', 'accounts', $p, $key)->assertJsonPath('meta.replayed', true)->assertJsonPath('data.id', $a['id']);
        $this->command('POST', 'accounts', array_replace($p, ['name' => 'Other']), $key)->assertStatus(409);
        $this->command('PATCH', 'accounts/'.$a['id'], ['expected_revision' => 1, 'currency_code' => 'USD'])->assertStatus(422);
        $this->command('POST', 'accounts/'.$a['id'].'/archive', ['expected_revision' => 1])->assertOk();
        $this->assertSame('1000', $this->balance($a));
        $this->command('POST', 'operations', ['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $this->category(), 'amount_minor' => '1', 'occurred_on' => '2026-10-02'])->assertStatus(422);
    }

    public function test_goal_spend_marker_void_and_lifetime(): void
    {
        $g = $this->command('POST', 'goals', ['name' => 'Trip', 'target_amount_minor' => '100000', 'currency_code' => 'MDL'])->assertOk()->json('data');
        $a = $this->account('100000');
        $this->recordOperation(['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $g['savings_account_id'], 'amount_minor' => '100000']);
        $r = $this->command('POST', 'goals/'.$g['id'].'/spend', ['expected_revision' => 1, 'amount_minor' => '40000', 'occurred_on' => '2026-10-02'])->assertOk();
        $r->assertJsonPath('data.goal.saved_now_minor', '60000')->assertJsonPath('data.goal.spent_on_goal_minor', '40000')->assertJsonPath('data.goal.funded_lifetime_minor', '100000')->assertJsonPath('data.goal.status', 'reached');
        $g = $r->json('data.goal');
        $o = $r->json('data.operation');
        $this->command('PATCH', 'goals/'.$g['id'].'/expenses/'.$o['id'], ['expected_revision' => $g['revision'], 'expected_operation_revision' => 1, 'amount_minor' => '40000', 'occurred_on' => '2026-10-02', 'goal_completion_requested' => true])->assertOk()->assertJsonPath('data.goal.status', 'spent');
        $this->command('POST', 'operations/'.$o['id'].'/void', ['expected_revision' => 2])->assertOk();
        $this->getJson('/api/v1/goals/'.$g['id'])->assertJsonPath('data.status', 'reached')->assertJsonPath('data.completed_at', null);
    }

    public function test_settlement_atomic_and_void(): void
    {
        $a = $this->account('0');
        $l = $this->command('POST', 'liabilities', ['kind' => 'receivable', 'counterparty_name' => 'Friend', 'principal_minor' => '30000', 'currency_code' => 'MDL'])->assertOk()->json('data');
        $r = $this->command('POST', 'liabilities/'.$l['id'].'/settle', ['expected_revision' => 1, 'account_id' => $a['id'], 'category_id' => $this->category('other_income'), 'occurred_on' => '2026-10-02'])->assertOk();
        $o = $r->json('data.operation');
        $this->command('PATCH', 'operations/'.$o['id'], ['expected_revision' => 1, 'amount_minor' => '10'])->assertStatus(409);
        $this->expense($a, '29000');
        $this->command('POST', 'liabilities/'.$l['id'].'/void-settlement', ['expected_revision' => 2])->assertStatus(409);
        $this->getJson('/api/v1/liabilities/'.$l['id'])->assertJsonPath('data.status', 'settled');
        $this->income($a, '30000');
        $this->command('POST', 'liabilities/'.$l['id'].'/void-settlement', ['expected_revision' => 2])->assertOk()->assertJsonPath('data.liability.status', 'open');
    }

    public function test_budget_currency_month_repeat_disabled(): void
    {
        $cid = $this->category();
        $tpl = $this->command('POST', 'budget-templates', ['category_id' => $cid, 'start_month' => '2026-10-01', 'limit_minor' => '1000', 'currency_code' => 'MDL'])->assertOk();
        $this->command('POST', 'budgets/ensure-month', ['period_month' => '2026-10-01'])->assertOk()->assertJsonPath('data.created', 1);
        $this->command('POST', 'budgets/ensure-month', ['period_month' => '2026-10-01'])->assertJsonPath('data.created', 0);
        $b = $this->getJson('/api/v1/budgets?month=2026-10')->assertOk()->json('data.items.0');
        $a = $this->account('1000', 'USD');
        $this->expense($a, '500');
        $this->getJson('/api/v1/budgets?month=2026-10')->assertJsonPath('data.items.0.fact_minor', '0')->assertJsonPath('data.items.0.other_currencies.USD', '500');
        $this->command('DELETE', 'budgets/'.$b['id'], ['expected_revision' => 1])->assertOk();
        $this->command('POST', 'budgets/ensure-month', ['period_month' => '2026-10-01'])->assertJsonPath('data.created', 0);
    }

    public function test_ownership_privileges_and_restore_tombstone(): void
    {
        $other = User::create(['full_name' => 'Other', 'email' => 'other@example.test', 'password' => 'password-long-123']);
        app(WorkspaceService::class)->initialize($other->id);
        $foreign = DB::table('accounts')->where('user_id', $other->id)->value('id');
        $this->owner->forceFill(['is_admin' => true])->save();
        $this->getJson('/api/v1/accounts/'.$foreign)->assertStatus(404);
        $a = $this->account('10000');
        $key = (string) Str::uuid();
        $p = ['type' => 'income', 'account_id' => $a['id'], 'category_id' => $this->category('salary'), 'amount_minor' => '1000', 'occurred_on' => '2026-10-02'];
        $this->command('POST', 'operations', $p, $key)->assertOk();
        $d = $this->getJson('/api/v1/backup/export')->assertOk()->json();
        $backup = app(BackupService::class);
        $invalid = $d;
        $invalid['is_admin'] = true;
        $this->expectPreviewError($invalid, 'UNKNOWN_FIELDS');
        $preview = $backup->preview($this->owner->id, json_encode($d));
        $this->command('POST', 'backup/apply', ['preview_token' => $preview['preview_token'], 'expected_workspace_revision' => $preview['expected_workspace_revision']])->assertOk();
        $this->assertSame('11000', $this->balance($a));
        $this->command('POST', 'operations', $p, $key)->assertStatus(409)->assertJsonPath('error.code', 'WORKSPACE_REPLACED');
    }

    private function expectPreviewError(array $d, string $code): void
    {
        try {
            app(BackupService::class)->preview($this->owner->id, json_encode($d));
            $this->fail('Expected backup error');
        } catch (DomainError $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }

    public function test_restore_stale_and_full_rollback(): void
    {
        $a = $this->account();
        $backup = app(BackupService::class);
        $d = $backup->export($this->owner->id);
        $p = $backup->preview($this->owner->id, json_encode($d));
        $this->income($a, '10');
        $this->command('POST', 'backup/apply', ['preview_token' => $p['preview_token'], 'expected_workspace_revision' => $p['expected_workspace_revision']])->assertStatus(409);
        $p = $backup->preview($this->owner->id, json_encode($d));
        DB::statement("CREATE TRIGGER test_restore_failure BEFORE INSERT ON accounts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected failure'");
        try {
            $this->command('POST', 'backup/apply', ['preview_token' => $p['preview_token'], 'expected_workspace_revision' => $p['expected_workspace_revision']])->assertStatus(500);
        } finally {
            DB::statement('DROP TRIGGER test_restore_failure');
        }
        $this->assertSame('100010', $this->balance($a));
    }

    public function test_password_reset_new_password_and_expired_token(): void
    {
        Notification::fake();
        $token = Password::createToken($this->owner);
        $this->postJson('/auth/reset-password', ['email' => $this->owner->email, 'token' => $token, 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456'])->assertOk();
        $this->assertTrue(Hash::check('new-password-456', $this->owner->fresh()->password));
        $this->postJson('/auth/reset-password', ['email' => $this->owner->email, 'token' => $token, 'password' => 'another-password-789', 'password_confirmation' => 'another-password-789'])->assertStatus(422);
    }

    public function test_rejected_shapes_currency_ownership_and_overdraft_have_no_effect(): void
    {
        $a = $this->account('1000');
        $b = $this->account('0', 'USD');
        $other = User::create(['full_name' => 'Other', 'email' => 'foreign@example.test', 'password' => 'password-long-123']);
        app(WorkspaceService::class)->initialize($other->id);
        $foreign = DB::table('accounts')->where('user_id', $other->id)->value('id');
        foreach ([
            ['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $a['id'], 'amount_minor' => '100'],
            ['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $foreign, 'amount_minor' => '100'],
            ['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $this->category(), 'amount_minor' => '100', 'currency_code' => 'USD'],
            ['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $this->category(), 'amount_minor' => '1001'],
        ] as $p) {
            $response = $this->command('POST', 'operations', $p + ['occurred_on' => '2026-10-02']);
            $this->assertContains($response->status(), [404, 409, 422]);
        }
        $this->assertSame('1000', $this->balance($a));
        $this->assertSame('0', $this->balance($b));
        $this->assertDatabaseCount('operations', 0);
    }

    public function test_transfer_account_changes_validate_old_and_new_accounts(): void
    {
        $a = $this->account('100000');
        $b = $this->account('0');
        $c = $this->account('0');
        $d = $this->account('1000');
        $o = $this->recordOperation(['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $b['id'], 'amount_minor' => '50000']);
        $this->expense($b, '45000');
        $this->command('PATCH', 'operations/'.$o['id'], ['expected_revision' => 1, 'to_account_id' => $c['id']])->assertStatus(409);
        $this->command('PATCH', 'operations/'.$o['id'], ['expected_revision' => 1, 'from_account_id' => $d['id']])->assertStatus(409);
        $this->assertSame('50000', $this->balance($a));
        $this->assertSame('5000', $this->balance($b));
        $this->assertSame('0', $this->balance($c));
        $this->assertSame('1000', $this->balance($d));
    }

    public function test_goal_nonpurpose_withdrawal_and_spend_failure_rollback(): void
    {
        $g = $this->command('POST', 'goals', ['name' => 'Trip', 'target_amount_minor' => '100000', 'currency_code' => 'MDL'])->json('data');
        $a = $this->account('100000');
        $this->recordOperation(['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $g['savings_account_id'], 'amount_minor' => '100000']);
        $this->recordOperation(['type' => 'transfer', 'from_account_id' => $g['savings_account_id'], 'to_account_id' => $a['id'], 'amount_minor' => '10000']);
        $this->getJson('/api/v1/goals/'.$g['id'])->assertJsonPath('data.funded_lifetime_minor', '90000')->assertJsonPath('data.progress', '90.00')->assertJsonPath('data.status', 'active');
        $before = DB::table('operations')->count();
        $revision = DB::table('user_settings')->where('user_id', $this->owner->id)->value('workspace_revision');
        DB::statement("CREATE TRIGGER test_goal_failure BEFORE UPDATE ON goals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected failure'");
        try {
            $this->command('POST', 'goals/'.$g['id'].'/spend', ['expected_revision' => 1, 'amount_minor' => '1000', 'occurred_on' => '2026-10-02', 'goal_completion_requested' => true])->assertStatus(500);
        } finally {
            DB::statement('DROP TRIGGER test_goal_failure');
        }
        $this->assertSame($before, DB::table('operations')->count());
        $this->assertSame($revision, DB::table('user_settings')->where('user_id', $this->owner->id)->value('workspace_revision'));
        $this->getJson('/api/v1/goals/'.$g['id'])->assertJsonPath('data.spent_on_goal_minor', '0')->assertJsonPath('data.completed_at', null);
    }

    public function test_payable_credit_settlement_retry_and_midcommand_failure(): void
    {
        $a = $this->account('100000');
        foreach (['payable', 'credit'] as $kind) {
            $l = $this->command('POST', 'liabilities', ['kind' => $kind, 'counterparty_name' => 'Bank', 'principal_minor' => '30000', 'currency_code' => 'MDL'])->json('data');
            $p = ['expected_revision' => 1, 'account_id' => $a['id'], 'category_id' => $this->category('other_expense'), 'occurred_on' => '2026-10-02'];
            $key = (string) Str::uuid();
            if ($kind === 'payable') {
                DB::statement("CREATE TRIGGER test_settlement_failure BEFORE INSERT ON liability_settlements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected failure'");
                try {
                    $this->command('POST', 'liabilities/'.$l['id'].'/settle', $p, $key)->assertStatus(500);
                } finally {
                    DB::statement('DROP TRIGGER test_settlement_failure');
                }
                $this->assertSame('100000', $this->balance($a));
                $this->assertDatabaseCount('operations', 0);
                $this->assertDatabaseCount('liability_settlements', 0);
            }
            $this->command('POST', 'liabilities/'.$l['id'].'/settle', $p, $key)->assertOk();
            $this->command('POST', 'liabilities/'.$l['id'].'/settle', $p, $key)->assertJsonPath('meta.replayed', true);
        }
        $this->assertSame('40000', $this->balance($a));
        $this->assertDatabaseCount('liability_settlements', 2);
        $this->getJson('/api/v1/liabilities')->assertJsonPath('data.totals.MDL.payable_minor', '0')->assertJsonPath('data.totals.MDL.credit_minor', '0');
    }

    public function test_archived_category_stops_future_template_and_remains_in_history(): void
    {
        $c = $this->command('POST', 'categories', ['name' => 'Custom', 'kind' => 'expense'])->json('data');
        $a = $this->account('1000');
        $this->recordOperation(['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $c['id'], 'amount_minor' => '100']);
        $tpl = $this->command('POST', 'budget-templates', ['category_id' => $c['id'], 'start_month' => '2027-01-01', 'limit_minor' => '1000', 'currency_code' => 'MDL'])->json('data');
        $this->command('POST', 'categories/'.$c['id'].'/archive', ['expected_revision' => 1])->assertOk();
        $this->command('POST', 'budgets/ensure-month', ['period_month' => '2027-01-01'])->assertJsonPath('data.created', 0);
        $this->getJson('/api/v1/budget-templates/'.$tpl['id'])->assertJsonPath('data.stop_month', '2027-01-01');
        $this->command('POST', 'categories/'.$c['id'].'/unarchive', ['expected_revision' => 2])->assertOk();
        $this->command('POST', 'budget-templates', ['category_id' => $c['id'], 'start_month' => '2027-01-01', 'limit_minor' => '2000', 'currency_code' => 'MDL'])->assertOk();
        $this->command('POST', 'categories/'.$c['id'].'/archive', ['expected_revision' => 3])->assertOk();
        $this->getJson('/api/v1/operations')->assertJsonPath('data.items.0.category_id', $c['id']);
        $this->command('POST', 'operations', ['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $c['id'], 'amount_minor' => '100', 'occurred_on' => '2026-10-02'])->assertStatus(422);
        app(BackupService::class)->preview($this->owner->id, json_encode(app(BackupService::class)->export($this->owner->id)));
    }

    public function test_balance_report_keeps_negative_reconstructed_history_and_original_precision(): void
    {
        $a = $this->account('0');
        $this->income($a, '1000');
        $this->recordOperation(['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $this->category(), 'amount_minor' => '800', 'occurred_on' => '2026-10-01']);
        $r = $this->getJson('/api/v1/reports/balances?date_from=2026-10-01&date_to=2026-10-02')->assertOk()->json('data.accounts');
        $points = collect($r)->firstWhere('account_id', $a['id'])['points'];
        $this->assertSame('-800', $points[0]['balance_minor']);
        $this->assertSame('200', $points[1]['balance_minor']);
        $this->assertSame('200', $this->balance($a));
    }

    public function test_restore_roundtrip_with_archives_voids_and_foreign_id_remapping(): void
    {
        $a = $this->account('10000');
        $o = $this->income($a, '1000');
        $this->command('POST', 'operations/'.$o['id'].'/void', ['expected_revision' => 1])->assertOk();
        $this->command('POST', 'accounts/'.$a['id'].'/archive', ['expected_revision' => 1])->assertOk();
        $document = app(BackupService::class)->export($this->owner->id);
        $other = User::create(['full_name' => 'Recipient', 'email' => 'recipient@example.test', 'password' => 'password-long-123']);
        $other->forceFill(['email_verified_at' => now()])->save();
        app(WorkspaceService::class)->initialize($other->id);
        Auth::forgetGuards();
        $this->withSession(['password_hash_web' => $other->password]);
        $this->actingAs($other);
        $document['accounts'][0]['name'] = $a['id'];
        $p = app(BackupService::class)->preview($other->id, json_encode($document));
        $r = $this->command('POST', 'backup/apply', ['preview_token' => $p['preview_token'], 'expected_workspace_revision' => $p['expected_workspace_revision']])->assertOk();
        $this->assertArrayHasKey($a['id'], $r->json('data.id_remapping'));
        $new = $r->json('data.id_remapping.'.$a['id']);
        $this->getJson('/api/v1/accounts/'.$new)->assertJsonPath('data.balance_minor', '10000');
        $this->getJson('/api/v1/accounts/'.$a['id'])->assertStatus(404);
        $copy = app(BackupService::class)->export($other->id);
        $this->assertContains($a['id'], array_column($copy['accounts'], 'name'));
        $this->assertSame('voided', $copy['operations'][0]['status']);
        $this->assertNotNull(collect($copy['accounts'])->firstWhere('id', $new)['archived_at']);
        $this->assertCount(2,$copy['operation_revisions']);
    }
}
