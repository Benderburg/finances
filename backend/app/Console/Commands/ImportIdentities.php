<?php

namespace App\Console\Commands;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\WorkspaceService;
use App\Migration\ExactJson;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportIdentities extends Command
{
    protected $signature = 'norocel:identities {file : Trusted server identity export, never a user backup} {--apply : Local/test rehearsal only}';

    protected $description = 'Prepare existing UUID Laravel identities for reset-password onboarding; sends no email';

    public function handle(WorkspaceService $workspace): int
    {
        if ($this->option('apply') && app()->environment('production')) {
            $this->error('Use the separately approved cutover procedure in production.');

            return self::FAILURE;
        }
        try {
            if (! is_file($this->argument('file')) || filesize($this->argument('file')) > 10 * 1024 * 1024) {
                throw new DomainError('BACKUP_LIMIT_EXCEEDED');
            }
            $source = ExactJson::decode(file_get_contents($this->argument('file')));
            if (($source['format'] ?? null) !== 'norocel.supabase-identities') {
                throw new DomainError('TRUSTED_IDENTITY_EXPORT_REQUIRED');
            }
            $rows = $source['users'] ?? [];
            if (! is_array($rows) || count($rows) > 100000) {
                throw new DomainError('INVALID_IDENTITY_EXPORT');
            }
            $ids = [];
            $emails = [];
            foreach ($rows as $r) {
                Fields::check($r, ['id' => 'required|uuid', 'email' => 'required|email|max:255', 'email_confirmed_at' => 'nullable|date', 'full_name' => 'required|string|max:255', 'billing_plan' => 'required|in:regular,premium', 'is_admin' => 'required|boolean']);
                $email = strtolower($r['email']);
                if (isset($ids[$r['id']]) || isset($emails[$email])) {
                    throw new DomainError('IDENTITY_MAPPING_CONFLICT');
                }
                $ids[$r['id']] = true;
                $emails[$email] = true;
                $existing = User::where('id', $r['id'])->orWhere('email', $email)->first();
                if ($existing && ($existing->id !== $r['id'] || strtolower($existing->email) !== $email)) {
                    throw new DomainError('IDENTITY_MAPPING_CONFLICT');
                }
            }
            if ($this->option('apply')) {
                foreach ($rows as $r) {
                    DB::transaction(function () use ($r, $workspace) {
                        $user = User::find($r['id']);
                        if ($user && strtolower($user->email) !== strtolower($r['email'])) {
                            throw new DomainError('IDENTITY_MAPPING_CONFLICT');
                        }
                        if (! $user) {
                            $user = new User(['email' => strtolower($r['email']), 'full_name' => $r['full_name'], 'password' => Str::random(64)]);
                            $user->forceFill(['id' => $r['id']]);
                            $user->save();
                        }
                        // Public mass assignment cannot choose IDs; this trusted server-only import can.
                        if ($user->id !== $r['id']) {
                            throw new DomainError('IDENTITY_MAPPING_CONFLICT');
                        }
                        $workspace->initialize($user->id);
                        DB::table('user_settings')->where('user_id', $user->id)->lockForUpdate()->first();
                        $user->forceFill(['full_name' => $r['full_name'], 'email_verified_at' => $r['email_confirmed_at'] ?? null, 'billing_plan' => $r['billing_plan'], 'is_admin' => $r['is_admin']])->save();
                        DB::table('user_settings')->where('user_id', $user->id)->increment('workspace_revision');
                    });
                }
            }
            $this->line(json_encode(['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'identities' => count($rows), 'password_reset_required' => true, 'emails_sent' => 0], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof DomainError ? $e->errorCode : 'IDENTITY_IMPORT_BLOCKED');

            return self::FAILURE;
        }
    }
}
