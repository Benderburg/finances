<?php

namespace App\Console\Commands;

use App\Domain\WorkspaceService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CreateLocalUser extends Command
{
    protected $signature = 'norocel:local-user {email} {--name=Local User} {--locale=ro} {--admin}';

    protected $description = 'Create a verified local/test user; password from NOROCEL_LOCAL_PASSWORD';

    public function handle(WorkspaceService $workspace): int
    {
        if (app()->environment('production') || ! getenv('NOROCEL_LOCAL_PASSWORD') || strlen(getenv('NOROCEL_LOCAL_PASSWORD')) < 12) {
            $this->error('Non-production only. Set NOROCEL_LOCAL_PASSWORD with at least 12 characters.');

            return self::FAILURE;
        }
        $user = DB::transaction(function () use ($workspace) {
            $user = User::where('email', $this->argument('email'))->first();
            if (! $user) {
                $user = User::create(['email' => $this->argument('email'), 'full_name' => $this->option('name'), 'password' => getenv('NOROCEL_LOCAL_PASSWORD')]);
            }
            $workspace->initialize($user->id);
            DB::table('user_settings')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! in_array($this->option('locale'), ['ro', 'ru', 'en'])) {
                throw new \RuntimeException('Invalid locale');
            }
            DB::table('user_settings')->where('user_id', $user->id)->update(['locale' => $this->option('locale')]);
            $user->forceFill(['email_verified_at' => now(), 'is_admin' => (bool) $this->option('admin')])->save();

            return $user;
        });
        $this->line($user->id);

        return self::SUCCESS;
    }
}
