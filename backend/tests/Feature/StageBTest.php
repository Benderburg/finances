<?php

namespace Tests\Feature;

use App\Domain\BnmRateProvider;
use App\Domain\CsvService;
use App\Domain\DomainError;
use App\Domain\FinancialEngine;
use App\Domain\ReferenceRates;
use App\Domain\WorkspaceService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class StageBTest extends TestCase
{
    use DatabaseTruncation;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = $this->user('owner-b@example.test');
        $this->actingAs($this->owner);
    }

    private function user(string $email): User
    {
        $user = User::create(['full_name' => 'Stage B', 'email' => $email, 'password' => 'password-long-123']);
        $user->forceFill(['email_verified_at' => now()])->save();
        app(WorkspaceService::class)->initialize($user->id);

        return $user;
    }

    private function command(string $method, string $path, array $p, ?string $key = null)
    {
        return $this->json($method, '/api/v1/'.$path, $p, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function account(string $currency = 'MDL', string $opening = '100000'): array
    {
        return $this->command('POST', 'accounts', ['name' => 'Account '.Str::random(6), 'kind' => 'regular', 'currency_code' => $currency, 'opening_balance_minor' => $opening])->assertOk()->json('data');
    }

    private function category(string $code = 'food'): string
    {
        return DB::table('categories')->where('user_id', $this->owner->id)->where('system_code', $code)->value('id');
    }

    private function xml(string $day = '01.10.2026', string $usd = '1800'): string
    {
        return '<ValCurs Date="'.$day.'"><Valute><CharCode>EUR</CharCode><Nominal>1</Nominal><Value>20</Value></Valute><Valute><CharCode>USD</CharCode><Nominal>100</Nominal><Value>'.$usd.'</Value></Valute><Valute><CharCode>RON</CharCode><Nominal>1</Nominal><Value>4</Value></Valute></ValCurs>';
    }

    private function rates(string $day = '2026-10-01', string $usd = '1800'): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([BnmRateProvider::URL.'*' => Http::response($this->xml(date('d.m.Y', strtotime($day)), $usd))]);
        $this->assertTrue(app(ReferenceRates::class)->sync($day, true)['available']);
    }

    private function bankOptions(array $a): array
    {
        return ['confirmed' => true, 'account_id' => $a['id'], 'mapping' => ['date' => 'date', 'amount' => 'amount', 'description' => 'description'], 'income_category_id' => $this->category('salary'), 'expense_category_id' => $this->category()];
    }

    private function preview(string $csv, array $options): array
    {
        Cache::flush();

        return $this->post('/api/v1/csv/preview', ['file' => UploadedFile::fake()->createWithContent('journal.csv', $csv), 'options' => json_encode($options)], ['Accept' => 'application/json'])->assertOk()->json('data');
    }

    private function apply(array $preview, array $rows, bool $duplicates = false, ?string $key = null)
    {
        Cache::flush();

        return $this->command('POST', 'csv/apply', ['preview_id' => $preview['preview_id'], 'selected_rows' => $rows, 'accept_possible_duplicates' => $duplicates], $key);
    }

    public function test_normalized_cross_rate_corrections_and_fallback_window(): void
    {
        $this->rates();
        $fx = app(ReferenceRates::class);
        $this->assertSame('18.000000000000000000', $fx->rate('USD', '2026-10-01')['mdl_per_unit']);
        $this->assertSame('9000', $fx->convert('10000', 'USD', 'EUR', '2026-10-01')['amount_minor']);
        $fallback = $fx->convert('10000', 'USD', 'EUR', '2026-10-08');
        $this->assertTrue($fallback['rates'][0]['fallback']);
        $this->assertSame('2026-10-01', $fallback['rates'][0]['effective_on']);
        $this->assertNull($fx->convert('10000', 'USD', 'EUR', '2026-10-09')['amount_minor']);
        $this->assertNull($fx->convert('10000', 'USD', 'EUR', '2026-09-30')['amount_minor']);
        $this->rates('2026-10-01', '1900');
        $this->assertSame(2, $fx->rate('USD', '2026-10-01')['version']);
        $this->assertSame(5, DB::table('fx_reference_rates')->count());
        $this->rates('2026-10-01', '1900');
        $this->assertSame(5, DB::table('fx_reference_rates')->count());
        $this->postJson('/api/v1/operations/quote', ['amount_minor' => '10000', 'currency_code' => 'USD', 'target_currency_code' => 'EUR', 'date' => '2026-10-01'])->assertOk()->assertJsonPath('data.amount_minor', '9500')->assertJsonPath('data.indicative', true);
    }

    public function test_provider_rejects_entities_incomplete_and_future_dates(): void
    {
        foreach (['<!DOCTYPE foo [<!ENTITY x SYSTEM "file:///etc/passwd">]>'.$this->xml(), str_replace('<CharCode>RON</CharCode>', '<CharCode>JPY</CharCode>', $this->xml()), $this->xml('02.10.2026'), str_replace('<Nominal>100</Nominal>', '<Nominal>0</Nominal>', $this->xml())] as $bad) {
            try {
                app(BnmRateProvider::class)->parse($bad, '2026-10-01');
                $this->fail('Unsafe/invalid XML accepted');
            } catch (DomainError) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_historical_flow_budget_row_rounding_and_base_currency_never_changes_money(): void
    {
        $a = $this->account('USD');
        $this->rates();
        $this->rates('2026-10-02', '2000');
        foreach (['2026-10-01', '2026-10-02'] as $day) {
            $this->command('POST', 'operations', ['type' => 'expense', 'account_id' => $a['id'], 'category_id' => $this->category(), 'amount_minor' => '101', 'occurred_on' => $day])->assertOk();
        }
        $budget = $this->command('POST', 'budgets', ['category_id' => $this->category(), 'period_month' => '2026-10-01', 'currency_code' => 'EUR', 'limit_minor' => '500'])->assertOk()->json('data');
        $this->assertSame('192', $budget['fact_minor']); // 90.9 -> 91 plus 101, never sum then convert.
        $this->getJson('/api/v1/reports/cash-flow?date_from=2026-10-01&date_to=2026-10-02&display_currency=EUR')->assertOk()->assertJsonPath('data.consolidated.known_subtotal.expense_minor', '192');
        $before = DB::table('operations')->get()->toJson();
        $this->command('PATCH', 'me', ['base_currency_code' => 'RON'])->assertOk();
        $this->getJson('/api/v1/dashboard?month=2026-10&valuation_date=2026-10-02')->assertOk()->assertJsonPath('data.consolidated.balances.currency_code', 'RON');
        $this->assertSame($before, DB::table('operations')->get()->toJson());
        $this->assertSame('500', (string) DB::table('budgets')->where('id', $budget['id'])->value('limit_minor'));
        $this->assertSame('99798', app(FinancialEngine::class)->balance($this->owner->id, $a['id']));
        $fx = app(ReferenceRates::class);
        $this->assertSame('4499999999999999996', $fx->convert('999999999999999999', 'USD', 'RON', '2026-10-01')['amount_minor']);
    }

    public function test_missing_rate_is_visible_and_financial_saves_never_call_provider(): void
    {
        $a = $this->account('USD');
        $this->command('POST', 'operations', ['type' => 'income', 'account_id' => $a['id'], 'category_id' => $this->category('salary'), 'amount_minor' => '10000', 'occurred_on' => '2020-01-01'])->assertOk();
        $r = $this->getJson('/api/v1/reports/cash-flow?date_from=2020-01-01&date_to=2020-01-01&display_currency=EUR')->assertOk()->json('data');
        $this->assertTrue($r['consolidated']['incomplete']);
        $this->assertSame('0', $r['consolidated']['known_subtotal']['income_minor']);
        $this->assertSame('10000', $r['consolidated']['unconverted'][0]['amount_minor']);
        $this->assertSame('10000', $r['currencies']['USD']['income_minor']);
        Http::assertNothingSent();
    }

    public function test_bank_detection_batch_replay_and_identical_real_purchases(): void
    {
        $a = $this->account();
        $csv = "date;amount;description\r\n2026-10-01;-1.00;Coffee\r\n2026-10-01;-1.00;Coffee\r\n";
        $detected = $this->preview($csv, []);
        $this->assertTrue($detected['needs_confirmation']);
        $this->assertSame(';', $detected['delimiter']);
        $this->assertSame(0, DB::table('operations')->count());
        $preview = $this->preview($csv, $this->bankOptions($a));
        $this->assertFalse($preview['rows'][0]['possible_duplicate']);
        $this->assertTrue($preview['rows'][1]['possible_duplicate']);
        $this->apply($preview, [1, 2])->assertStatus(422)->assertJsonPath('error.code', 'CSV_DUPLICATES_CONFIRMATION');
        $key = (string) Str::uuid();
        $this->apply($preview, [1, 2], true, $key)->assertOk()->assertJsonPath('data.imported', 2);
        $this->apply($preview, [1, 2], true, $key)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertSame(2, DB::table('operations')->count());
        $again = $this->preview($csv, $this->bankOptions($a));
        $this->assertContains('CSV_STRICT_DUPLICATE', $again['rows'][0]['errors']);
        $differentUpload = $this->preview("date,amount,description\n2026-10-01,-1.00,Coffee\n", $this->bankOptions($a));
        $this->assertTrue($differentUpload['rows'][0]['possible_duplicate']);
        $this->apply($differentUpload, [1], true)->assertOk();
        $this->assertSame(3, DB::table('operations')->count());
    }

    public function test_final_balance_all_or_nothing_and_stale_preview(): void
    {
        $a = $this->account('MDL', '0');
        $p = $this->preview("date,amount,description\n2026-10-01,-2.00,expense\n2026-10-01,3.00,income\n", $this->bankOptions($a));
        $this->apply($p, [1, 2])->assertOk();
        $this->assertSame('100', app(FinancialEngine::class)->balance($this->owner->id, $a['id']));
        $bad = $this->preview("date,amount,description\n2026-10-01,-2.00,too much\n2026-10-01,0.50,too little\n", $this->bankOptions($a));
        $counts = [DB::table('operations')->count(), DB::table('operation_revisions')->count(), DB::table('csv_import_rows')->count(), DB::table('command_deduplication')->count()];
        $this->apply($bad, [1, 2])->assertStatus(409)->assertJsonPath('error.code', 'INSUFFICIENT_FUNDS');
        $this->assertSame($counts, [DB::table('operations')->count(), DB::table('operation_revisions')->count(), DB::table('csv_import_rows')->count(), DB::table('command_deduplication')->count()]);
        $this->command('PATCH', 'me', ['full_name' => 'Changed'])->assertOk();
        $this->apply($bad, [2])->assertStatus(409)->assertJsonPath('error.code', 'CSV_PREVIEW_STALE');
    }

    public function test_unique_bank_id_transfer_rejection_currency_and_other_owner(): void
    {
        $a = $this->account();
        $o = $this->bankOptions($a);
        $o['mapping'] += ['transaction_id' => 'id', 'direction' => 'direction', 'currency' => 'currency'];
        $csv = "date,amount,description,id,direction,currency\n2026-10-01,1.00,Salary,B1,income,MDL\n2026-10-01,1.00,Transfer,B2,transfer,MDL\n2026-10-01,1.00,USD,B3,income,USD\n";
        $p = $this->preview($csv, $o);
        $this->assertContains('CSV_TRANSFER_OR_DIRECTION', $p['rows'][1]['errors']);
        $this->assertContains('CURRENCY_MISMATCH', $p['rows'][2]['errors']);
        $this->apply($p, [1])->assertOk();
        $p = $this->preview("date,amount,description,id,direction,currency\n2026-10-02,2.00,Changed,B1,income,MDL\n", $o);
        $this->assertContains('CSV_STRICT_DUPLICATE', $p['rows'][0]['errors']);
        $another = $this->user('other-b@example.test');
        Auth::forgetGuards();
        $this->withSession(['password_hash_web' => $another->password]);
        $this->actingAs($another);
        $this->apply($p, [1])->assertStatus(409)->assertJsonPath('error.code', 'CSV_PREVIEW_EXPIRED');
        $p = $this->preview($csv, $o);
        $this->assertContains('NOT_FOUND', $p['rows'][0]['errors']);
    }

    public function test_own_csv_safe_multiline_quotes_exchange_transfer_void_and_strict_ids(): void
    {
        $a = $this->account();
        $b = $this->account('USD', '0');
        $c = $this->account('MDL', '0');
        $description = "=1+1\n\"quoted\", ';tail";
        $this->command('POST', 'operations', ['type' => 'income', 'account_id' => $a['id'], 'category_id' => $this->category('salary'), 'amount_minor' => '100', 'occurred_on' => '2026-10-01', 'description' => $description])->assertOk();
        $this->command('POST', 'operations', ['type' => 'exchange', 'from_account_id' => $a['id'], 'to_account_id' => $b['id'], 'amount_minor' => '1800', 'target_amount_minor' => '100', 'occurred_on' => '2026-10-01'])->assertOk();
        $op = $this->command('POST', 'operations', ['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $c['id'], 'amount_minor' => '100', 'occurred_on' => '2026-10-01'])->assertOk()->json('data.operation');
        $this->command('POST', 'operations/'.$op['id'].'/void', ['expected_revision' => 1])->assertOk();
        $csv = $this->get('/api/v1/operations/export.csv')->assertOk()->getContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('#norocel-csv,1,excel-safe', $csv);
        $p = $this->preview($csv, ['confirmed' => true]);
        foreach ($p['rows'] as $r) {
            $this->assertContains('CSV_STRICT_DUPLICATE', $r['errors']);
        }
        $this->owner = $this->user('roundtrip-b@example.test');
        Auth::forgetGuards();
        $this->withSession(['password_hash_web' => $this->owner->password]);
        $this->actingAs($this->owner);
        $x = $this->account();
        $y = $this->account('USD', '0');
        $z = $this->account('MDL', '0');
        $p = $this->preview($csv, ['confirmed' => true, 'account_mappings' => [$a['id'] => $x['id'], $b['id'] => $y['id'], $c['id'] => $z['id']]]);
        foreach ($p['rows'] as $r) {
            $this->assertSame([], $r['errors']);
        }
        $this->apply($p, [1, 2, 3])->assertOk();
        $this->assertSame($description, DB::table('operations')->where('user_id', $this->owner->id)->where('type', 'income')->value('description'));
        $this->assertSame('100', app(FinancialEngine::class)->balance($this->owner->id, $y['id']));
        $this->assertSame('0', app(FinancialEngine::class)->balance($this->owner->id, $z['id']));
        $again = $this->preview($csv, ['confirmed' => true, 'account_mappings' => [$a['id'] => $x['id'], $b['id'] => $y['id'], $c['id'] => $z['id']]]);
        foreach ($again['rows'] as $r) {
            $this->assertContains('CSV_STRICT_DUPLICATE', $r['errors']);
        }
    }

    public function test_csv_limits_encoding_and_workspace_generation(): void
    {
        $a = $this->account('RON');
        $csv = iconv('UTF-8', 'Windows-1251', "date;amount;description\n2026-10-01;-1.00;Кофе\n");
        $this->assertSame('Windows-1251', $this->preview($csv, [])['encoding']);
        $p = $this->preview($csv, $this->bankOptions($a));
        $this->assertSame('Кофе', $p['rows'][0]['operation']['description']);
        DB::table('user_settings')->where('user_id', $this->owner->id)->increment('workspace_generation');
        $this->apply($p, [1])->assertStatus(409)->assertJsonPath('error.code', 'WORKSPACE_REPLACED');
        try {
            app(CsvService::class)->preview($this->owner->id, "date,amount\n".str_repeat("2026-10-01,1\n", 10001), []);
            $this->fail('Row limit not enforced');
        } catch (DomainError $e) {
            $this->assertSame('CSV_LIMIT', $e->errorCode);
        }
        try {
            app(CsvService::class)->preview($this->owner->id, str_repeat('x', 5 * 1024 * 1024 + 1), []);
            $this->fail('Byte limit not enforced');
        } catch (DomainError $e) {
            $this->assertSame('CSV_LIMIT', $e->errorCode);
        }
    }

    public function test_own_csv_context_is_validated_and_linked_atomically(): void
    {
        $a = $this->account();
        $g = $this->command('POST', 'goals', ['name' => 'Goal', 'target_amount_minor' => '1000', 'currency_code' => 'MDL'])->assertOk()->json('data');
        $this->command('POST', 'operations', ['type' => 'transfer', 'from_account_id' => $a['id'], 'to_account_id' => $g['savings_account_id'], 'amount_minor' => '1000', 'occurred_on' => '2026-10-01'])->assertOk();
        $l = $this->command('POST', 'liabilities', ['kind' => 'payable', 'counterparty_name' => 'Person', 'principal_minor' => '500', 'currency_code' => 'MDL'])->assertOk()->json('data');
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['#norocel-csv', '1', 'raw'], ',', '"', '');
        fputcsv($stream, CsvService::HEADERS, ',', '"', '');
        foreach ([['goal' => $g['id'], 'account' => $g['savings_account_id'], 'category' => 'goal_expense', 'amount' => '1.00', 'liability' => '', 'complete' => '1'], ['goal' => '', 'account' => $a['id'], 'category' => 'food', 'amount' => '5.00', 'liability' => $l['id'], 'complete' => '0'], ['goal' => (string) Str::uuid(), 'account' => $g['savings_account_id'], 'category' => 'goal_expense', 'amount' => '1.00', 'liability' => '', 'complete' => '0']] as $r) {
            fputcsv($stream, [(string) Str::uuid(), 'expense', '2026-10-01', $r['account'], '', $r['amount'], 'MDL', $r['category'], '', 'CSV context', '', '', '', '', '', '', '', 'posted', $r['goal'], $r['liability'], $r['complete']], ',', '"', '');
        }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        $p = $this->preview($csv, ['confirmed' => true]);
        $this->assertSame([], $p['rows'][0]['errors']);
        $this->assertSame([], $p['rows'][1]['errors']);
        $this->assertContains('CSV_CONTEXT_REQUIRES_JSON', $p['rows'][2]['errors']);
        $this->apply($p, [1, 2])->assertOk();
        $this->getJson('/api/v1/goals/'.$g['id'])->assertOk()->assertJsonPath('data.status', 'spent')->assertJsonPath('data.spent_on_goal_minor', '100')->assertJsonPath('data.revision', 2);
        $this->getJson('/api/v1/liabilities/'.$l['id'])->assertOk()->assertJsonPath('data.status', 'settled')->assertJsonPath('data.revision', 2);
        $linked = DB::table('liability_settlements')->where('user_id', $this->owner->id)->where('liability_id', $l['id'])->value('operation_id');
        $this->command('PATCH', 'operations/'.$linked, ['expected_revision' => 1, 'amount_minor' => '1'])->assertStatus(409)->assertJsonPath('error.code', 'SETTLEMENT_COMMAND_REQUIRED');
        $bad = $this->preview("date,amount,description\n2026-10-01,--1.00,bad sign\n2026-10-01,-1.00,Bank transfer\n", $this->bankOptions($a));
        $this->assertContains('INVALID_MONEY', $bad['rows'][0]['errors']);
        $this->assertContains('CSV_TRANSFER_OR_DIRECTION', $bad['rows'][1]['errors']);
    }
}
