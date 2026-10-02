<?php

namespace Tests\Feature;

use App\Domain\WorkspaceService;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTruncation;

    private function user(): User
    {
        $u = User::create(['full_name' => 'Member', 'email' => 'member@example.test', 'password' => 'password-long-123']);
        app(WorkspaceService::class)->initialize($u->id);

        return $u;
    }

    public function test_registration_verification_login_logout_and_privilege_rejection(): void
    {
        Notification::fake();
        $p = ['full_name' => 'New <script>', 'email' => 'new@example.test', 'password' => 'password-long-123', 'password_confirmation' => 'password-long-123'];
        $this->postJson('/auth/register', $p + ['is_admin' => true])->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
        $r = $this->postJson('/auth/register', $p)->assertCreated();
        $u = User::findOrFail($r->json('data.id'));
        Notification::assertSentTo($u, VerifyEmail::class);
        $this->assertDatabaseCount('categories', 15);
        $this->assertDatabaseCount('accounts', 1);
        $this->getJson('/api/v1/accounts')->assertForbidden();
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->get($link)->assertRedirect('/?verified=1');
        Auth::forgetGuards();
        $this->getJson('/api/v1/accounts')->assertOk();
        $this->postJson('/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/auth/login', ['email' => $u->email, 'password' => 'incorrect'])->assertStatus(422);
        $this->postJson('/auth/login', ['email' => $u->email, 'password' => 'password-long-123'])->assertOk();
    }

    public function test_expired_password_token_and_email_confirmation(): void
    {
        Notification::fake();
        $u = $this->user();
        $token = Password::createToken($u);
        DB::table('password_reset_tokens')->where('email', $u->email)->update(['created_at' => now()->subHours(2)]);
        $this->postJson('/auth/reset-password', ['email' => $u->email, 'token' => $token, 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456'])->assertStatus(422);
        $this->assertTrue(Hash::check('password-long-123', $u->fresh()->password));
        $this->actingAs($u);
        $this->postJson('/auth/email-change', ['email' => 'changed@example.test', 'current_password' => 'incorrect'])->assertStatus(422);
        $this->postJson('/auth/email-change', ['email' => 'changed@example.test', 'current_password' => 'password-long-123'])->assertOk();
        $this->assertSame('member@example.test', $u->fresh()->email);
        $link = URL::temporarySignedRoute('email.confirm', now()->addMinutes(60), ['id' => $u->id, 'hash' => sha1('changed@example.test')]);
        $this->get($link)->assertRedirect();
        $this->assertSame('changed@example.test', $u->fresh()->email);
        $this->assertNull($u->fresh()->pending_email);
        $this->get($link)->assertForbidden();
    }

    public function test_admin_manage_roles_audit_and_public_profile_cannot_change_privileges(): void
    {
        $u = $this->user();
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($u);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson('/api/v1/me', ['is_admin' => true], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
        $admin = User::create(['email' => 'admin@example.test', 'full_name' => 'Admin', 'password' => 'password-long-123']);
        app(WorkspaceService::class)->initialize($admin->id);
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        Auth::forgetGuards();
        $this->withSession(['password_hash_web' => $admin->password]);
        $this->actingAs($admin);
        $this->patchJson('/api/v1/admin/users/'.$u->id, ['full_name' => 'Renamed', 'billing_plan' => 'premium', 'is_admin' => true], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $this->assertSame('premium', $u->fresh()->billing_plan);
        $this->assertDatabaseCount('admin_audit', 1);
        $this->getJson('/api/v1/accounts/'.DB::table('accounts')->where('user_id',$u->id)->value('id'))->assertNotFound();
    }
}
