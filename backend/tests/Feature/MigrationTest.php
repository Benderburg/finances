<?php

namespace Tests\Feature;

use App\Domain\BackupService;
use App\Domain\DomainError;
use App\Domain\WorkspaceService;
use App\Migration\ExactJson;
use App\Migration\LegacyNorocelAdapter;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use DatabaseTruncation;

    private function source(): array
    {
        return ExactJson::decode(file_get_contents(base_path('tests/fixtures/legacy-workspace.json')));
    }
    public function test_exact_custom_category_key_precedes_translated_system_alias(): void {
        $source=$this->source();$id=(string)\Illuminate\Support\Str::uuid();$source['categories'][]=['id'=>$id,'key'=>'Salary','name'=>'Custom Salary','type'=>'income'];
        foreach($source['transactions'] as &$row)if($row['type']==='income')$row['category']='Salary';unset($row);
        $result=app(LegacyNorocelAdapter::class)->convert($source,'2026-10');$income=collect($result['document']['operations'])->firstWhere('type','income');$this->assertSame($id,$income['category_id']);
    }

    public function test_fixture_preserves_money_fx_dates_and_ignored_privileges(): void
    {
        $result = app(LegacyNorocelAdapter::class)->convert($this->source(), '2026-10');
        $this->assertSame('15000', $result['reconciliation'][0]['balance_minor']);
        $this->assertSame('10000', $result['reconciliation'][1]['balance_minor']);
        foreach ($result['reconciliation'] as $b) {
            $this->assertSame('0', $b['delta_minor']);
        }
        $fx = $result['document']['operations'][1];
        $this->assertSame('180000', $fx['amount_minor']);
        $this->assertSame('10000', $fx['target_amount_minor']);
        $this->assertSame('18.000000000000', $fx['effective_rate']);
        $this->assertSame('17.5', $fx['legacy_metadata']['original_rate']);
        $this->assertContains('LEGACY_PROFILE_FIELD_IGNORED', array_column($result['warnings'], 'code'));
        $user = User::create(['email' => 'migration@example.test', 'full_name' => 'Migrated', 'password' => 'long-password-123']);
        app(WorkspaceService::class)->initialize($user->id);
        $this->artisan('norocel:migrate', ['file' => base_path('tests/fixtures/legacy-workspace.json'), '--user' => $user->id, '--month' => '2026-10', '--apply' => true])->assertSuccessful();
        $count = DB::table('operations')->where('user_id', $user->id)->count();
        $this->assertSame(3, $count);
        $this->artisan('norocel:migrate', ['file' => base_path('tests/fixtures/legacy-workspace.json'), '--user' => $user->id, '--month' => '2026-10', '--apply' => true])->assertSuccessful();
        $this->assertSame(3, DB::table('operations')->where('user_id', $user->id)->count());
        $this->artisan('norocel:migrate', ['file' => base_path('tests/fixtures/legacy-workspace.json'), '--user' => $user->id, '--month' => '2026-11', '--apply' => true])->assertFailed();
        $this->assertSame(3, DB::table('operations')->where('user_id', $user->id)->count());
        $backup=app(BackupService::class);$preview=$backup->preview($user->id,json_encode($backup->export($user->id)));
        app(\App\Domain\CommandBus::class)->execute($user->id,(string)\Illuminate\Support\Str::uuid(),'test-restore',[],fn()=> $backup->apply($user->id,['preview_token'=>$preview['preview_token'],'expected_workspace_revision'=>$preview['expected_workspace_revision']]));
        $this->artisan('norocel:migrate',['file'=>base_path('tests/fixtures/legacy-workspace.json'),'--user'=>$user->id,'--month'=>'2026-10','--apply'=>true])->assertFailed();
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame('regular', $user->fresh()->billing_plan);
    }

    public function test_precision_currency_and_broken_settlement_are_blockers(): void
    {
        foreach (['precision', 'currency', 'settlement'] as $kind) {
            $source = $this->source();
            if ($kind === 'precision') {
                $source['accounts'][0]['openingBalance'] = '1000.001';
            }
            if ($kind === 'currency') {
                $source['accounts'][0]['currencyCode'] = 'EUR';
            }
            if ($kind === 'settlement') {
                $source['liabilities'][] = ['id' => '88888888-8888-4888-8888-888888888888', 'type' => 'payable', 'counterpartyName' => 'Broken', 'amount' => '5', 'currencyCode' => 'MDL', 'status' => 'settled', 'settlementAccountId' => $source['accounts'][0]['id'], 'settlementTransactionId' => '99999999-9999-4999-8999-999999999999'];
            }
            try {
                app(LegacyNorocelAdapter::class)->convert($source, '2026-10');
                $this->fail('Expected blocker');
            } catch (DomainError $e) {
                $this->assertContains($e->errorCode, ['INVALID_MONEY', 'CURRENCY_OR_CATEGORY_MISMATCH', 'UNPROVEN_LEGACY_SETTLEMENT']);
            }
        }
    }

    public function test_trusted_identity_rehearsal_preserves_uuid_and_sends_no_mail(): void
    {
        Notification::fake();
        $id = (string) Str::uuid();
        $file = sys_get_temp_dir().'/norocel-identities-'.$id.'.json';
        file_put_contents($file, json_encode(['format' => 'norocel.supabase-identities', 'users' => [['id' => $id, 'email' => 'trusted@example.test', 'email_confirmed_at' => '2026-10-01T00:00:00Z', 'full_name' => 'Trusted Name', 'billing_plan' => 'premium', 'is_admin' => true]]]));
        try {
            $this->artisan('norocel:identities', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('users', 0);
            $this->artisan('norocel:identities', ['file' => $file, '--apply' => true])->assertSuccessful();
            $user = User::findOrFail($id);
            $this->assertSame('Trusted Name', $user->full_name);
            $this->assertTrue($user->is_admin);
            $this->assertSame('premium', $user->billing_plan);
            $this->artisan('norocel:identities', ['file' => $file, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('categories', 15);
            Notification::assertNothingSent();
        } finally {
            unlink($file);
        }
    }
}
