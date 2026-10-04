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
use Symfony\Component\Mailer\Exception\TransportException;
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
        $this->get('/api/v1/accounts')->assertForbidden()->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
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

    public function test_failed_verification_mail_preserves_registration_and_recovery_session(): void
    {
        Notification::shouldReceive('send')->andThrow(new TransportException('private transport diagnostic'));
        $payload = ['full_name' => 'Mail retry', 'email' => ' Retry@Example.Test ', 'password' => 'password-long-123', 'password_confirmation' => 'password-long-123', 'locale' => 'ru'];
        $response = $this->postJson('/auth/register', $payload)->assertCreated()->assertJsonPath('verification_sent', false);
        $user = User::findOrFail($response->json('data.id'));
        $this->assertSame('retry@example.test', $user->email);
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.settings.locale', 'ru');
        $this->getJson('/api/v1/accounts')->assertForbidden();
        $this->postJson('/auth/resend-verification')->assertStatus(503)->assertJsonPath('error.code', 'MAIL_DELIVERY_FAILED');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->postJson('/auth/register', $payload)->assertStatus(422);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('accounts', 1);
        Notification::fake();
        $this->travel(61)->seconds();
        $this->postJson('/auth/resend-verification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_first_email_confirmation_signs_in_from_another_browser_but_replay_does_not(): void
    {
        $user = $this->user();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->assertGuest();
        $this->get($url)->assertRedirect('/?verified=1');
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/v1/accounts')->assertOk();
        $this->postJson('/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->get($url)->assertRedirect('/login?verified=1');
        $this->assertGuest();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_invalid_or_expired_verification_links_do_not_verify_or_sign_in(): void
    {
        $user = $this->user();
        $args = ['id' => $user->id, 'hash' => sha1($user->email)];
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), $args);
        $this->get($expired)->assertRedirect('/login?verification=invalid');
        $signed = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), $args);
        $this->get($signed.'tampered')->assertRedirect('/login?verification=invalid');
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('another@example.test')]);
        $this->get($wrongHash)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_confirmation_does_not_switch_another_authenticated_account(): void
    {
        $user = $this->user();
        $other = User::create(['email' => 'other@example.test', 'full_name' => 'Other', 'password' => 'password-long-123']);
        app(WorkspaceService::class)->initialize($other->id);
        $this->actingAs($other);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/login?verified=1');
        $this->assertAuthenticatedAs($other);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resending_is_throttled_and_verification_message_matches_locale(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->actingAs($user);
        $this->postJson('/auth/resend-verification')->assertOk();
        $this->postJson('/auth/resend-verification')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        DB::table('user_settings')->where('user_id', $user->id)->update(['locale' => 'ru']);
        $message = (new VerifyEmail)->toMail($user);
        $this->assertSame('Norocel: подтвердите email', $message->subject);
        $this->assertSame('Подтвердить email и войти', $message->actionText);
        $this->assertStringContainsString('/auth/verify-email/'.$user->id.'/', $message->actionUrl);
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
