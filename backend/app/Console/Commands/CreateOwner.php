<?php

namespace App\Console\Commands;

use App\Domain\WorkspaceService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CreateOwner extends Command
{
    protected $signature = 'norocel:owner {email} {--name=Owner} {--locale=ru} {--password-file=}';

    protected $description = 'Initialize the first verified administrator on a fresh database; never resets existing credentials';

    public function handle(WorkspaceService $workspace): int
    {
        $profile = ['email' => strtolower(trim($this->argument('email'))), 'full_name' => $this->option('name'), 'locale' => $this->option('locale')];
        if (Validator::make($profile, ['email' => 'required|email|max:255', 'full_name' => 'required|string|max:255', 'locale' => 'required|in:ro,ru,en'])->fails()) {
            $this->error('Invalid owner email, name or locale.');

            return self::FAILURE;
        }
        $existing = User::where('email', $profile['email'])->first();
        if ($existing && $existing->is_admin && $existing->email_verified_at) {
            $this->line('Owner already initialized: '.$existing->id);

            return self::SUCCESS;
        }
        if (User::exists()) {
            $this->error('Initial owner requires a fresh database. Existing accounts and roles were not changed.');

            return self::FAILURE;
        }
        $file = $this->option('password-file');
        if ($file) {
            $path = realpath($file);
            $public = realpath(public_path());
            if (!$path || !is_file($path) || !is_readable($path) || ($public && str_starts_with(strtolower($path), strtolower($public.DIRECTORY_SEPARATOR)))) {
                $this->error('Password file must be readable and outside the public directory.');

                return self::FAILURE;
            }
            $password = rtrim(file_get_contents($path), "\r\n");
        } else {
            $password = $this->secret('Owner password (12–72 bytes)');
            if ($password !== $this->secret('Confirm owner password')) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || preg_match('/[\r\n\x00]/', $password)) {
            $this->error('Password must contain 12–72 bytes without line breaks or NUL.');

            return self::FAILURE;
        }
        $id = DB::transaction(function () use ($workspace, $profile, $password) {
            if (User::orderBy('id')->lockForUpdate()->first()) {
                return null;
            }
            $user = User::create(['email' => $profile['email'], 'full_name' => $profile['full_name'], 'password' => $password]);
            $workspace->initialize($user->id);
            DB::table('user_settings')->where('user_id', $user->id)->lockForUpdate()->first();
            DB::table('user_settings')->where('user_id', $user->id)->update(['locale' => $profile['locale']]);
            $user->forceFill(['email_verified_at' => now(), 'is_admin' => true])->save();

            return $user->id;
        }, 3);
        if (!$id) {
            $this->error('Database is no longer empty. No account was changed.');

            return self::FAILURE;
        }
        $this->line('Owner initialized: '.$id);

        return self::SUCCESS;
    }
}
