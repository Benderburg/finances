<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class OwnerTest extends TestCase
{
    use DatabaseTruncation;

    public function test_first_owner_is_initialized_once_without_mail_or_password_reset(): void
    {
        Notification::fake();
        $file = tempnam(sys_get_temp_dir(), 'norocel-owner-');
        try {
            file_put_contents($file, 'initial-owner-password-123');
            $args = ['email' => 'Owner@example.test', '--name' => 'Prototype Owner', '--locale' => 'ru', '--password-file' => $file];
            $this->artisan('norocel:owner', $args)->assertSuccessful();
            $owner = User::sole();
            $this->assertSame('owner@example.test', $owner->email);
            $this->assertTrue($owner->is_admin);
            $this->assertNotNull($owner->email_verified_at);
            $this->assertTrue(Hash::check('initial-owner-password-123', $owner->password));
            $this->assertDatabaseCount('accounts', 1);
            $this->assertDatabaseCount('categories', 15);
            $this->assertDatabaseCount('operations', 0);
            $this->assertDatabaseHas('user_settings', ['user_id' => $owner->id, 'locale' => 'ru']);
            file_put_contents($file, 'different-owner-password-456');
            $this->artisan('norocel:owner', $args)->assertSuccessful();
            $this->assertTrue(Hash::check('initial-owner-password-123', $owner->fresh()->password));
            $this->artisan('norocel:owner', array_replace($args, ['email' => 'other@example.test']))->assertFailed();
            $this->assertDatabaseCount('users', 1);
            Notification::assertNothingSent();
        } finally {
            unlink($file);
        }
    }

    public function test_invalid_bootstrap_does_not_create_an_owner(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'norocel-owner-');
        try {
            file_put_contents($file, 'short');
            $this->artisan('norocel:owner', ['email' => 'owner@example.test', '--password-file' => $file])->assertFailed();
            $this->artisan('norocel:owner', ['email' => 'owner@example.test', '--locale' => 'unknown', '--password-file' => $file])->assertFailed();
            $this->assertDatabaseCount('users', 0);
        } finally {
            unlink($file);
        }
    }
}
