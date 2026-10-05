<?php

namespace Noros\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Noros\Core\Models\Role;
use Noros\Core\Models\User;

class CreateAdmin extends Command
{
    protected $signature = 'noros:admin {email?} {--name=}';

    protected $description = 'Create an administrator using a securely prompted password';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email');
        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->secret('Password (at least 12 characters)');
        $data = Validator::make(compact('email', 'name', 'password'), ['email' => 'required|email|unique:users,email', 'name' => 'required|string|max:255', 'password' => 'required|string|min:12'])->validate();
        DB::transaction(function () use ($data): void {
            $role = Role::firstOrCreate(['name' => 'administrator'], ['permissions' => config('noros.permissions')]);
            $model = config('auth.providers.users.model', User::class);
            $model::create($data)->roles()->syncWithoutDetaching([$role->id]);
        });
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
