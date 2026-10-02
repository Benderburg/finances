<?php

namespace App\Domain;

use App\Migration\ExactJson;
use App\Migration\LegacyNorocelAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BackupService
{
    public function __construct(private readonly WorkspaceDocument $validator, private readonly FinancialEngine $engine, private readonly Projection $projection) {}

    public function export(string $user): array
    {
        $settings = (array) DB::table('user_settings')->where('user_id', $user)->first();
        $d = ['schema' => 'norocel.workspace', 'version' => 2, 'exported_at' => now()->toIso8601String(), 'settings' => array_intersect_key($settings, array_flip(['locale', 'base_currency_code', 'theme', 'timezone']))];
        foreach (WorkspaceDocument::TABLES as $t) {
            $d[$t] = DB::table($t)->where('user_id', $user)->orderBy('id')->get()->map(function ($r) {
                $r = Projection::serialize($r);
                unset($r['actor_id']);

                return $r;
            })->all();
        }

        return $d;
    }

    public function preview(string $user, string $contents, ?string $legacyMonth = null): array
    {
        if (strlen($contents) > 10 * 1024 * 1024) {
            throw new DomainError('BACKUP_LIMIT_EXCEEDED');
        }
        $d = ExactJson::decode($contents);
        $warnings = [];
        $reconciliation = null;
        if (! isset($d['schema'])) {
            $adapted = app(LegacyNorocelAdapter::class)->convert($d, $legacyMonth);
            $d = $adapted['document'];
            $warnings = $adapted['warnings'];
            $reconciliation = $adapted['reconciliation'];
        }
        $manifest = $this->validator->validate($d);
        $manifest['warnings'] = $warnings;
        $manifest['reconciliation'] = $reconciliation;
        $revision = DB::table('user_settings')->where('user_id', $user)->value('workspace_revision');
        $id = (string) Str::uuid();
        $hash = hash('sha256', $contents);
        $expires = now()->addMinutes(30);
        DB::table('import_previews')->insert(['id' => $id, 'user_id' => $user, 'file_hash' => $hash, 'payload' => json_encode($d, JSON_THROW_ON_ERROR), 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'expected_workspace_revision' => $revision, 'expires_at' => $expires, 'created_at' => now()]);

        return $manifest + ['preview_token' => $id, 'file_hash' => $hash, 'expected_workspace_revision' => (int) $revision, 'expires_at' => $expires->toIso8601String()];
    }

    public function apply(string $user, array $p): array
    {
        Fields::check($p, ['preview_token' => 'required|uuid', 'expected_workspace_revision' => 'required|integer|min:1']);
        $preview = DB::table('import_previews')->where('user_id', $user)->where('id', $p['preview_token'])->first();
        if (! $preview || now()->greaterThan($preview->expires_at)) {
            throw new DomainError('PREVIEW_EXPIRED', 409);
        }
        $settings = DB::table('user_settings')->where('user_id', $user)->first();
        if ($settings->workspace_revision != $preview->expected_workspace_revision || $p['expected_workspace_revision'] != $preview->expected_workspace_revision) {
            throw new DomainError('STALE_WORKSPACE', 409);
        }
        $d = json_decode($preview->payload, true, 512, JSON_THROW_ON_ERROR);
        $manifest = $this->validator->validate($d);
        $backup = (string) Str::uuid();
        DB::table('restore_backups')->insert(['id' => $backup, 'user_id' => $user, 'payload' => json_encode($this->export($user), JSON_THROW_ON_ERROR), 'created_at' => now()]);
        foreach (array_reverse(WorkspaceDocument::TABLES) as $table) {
            DB::table($table)->where('user_id', $user)->delete();
        }
        // Foreign UUID collisions are remapped explicitly, including nested audit operation IDs.
        $mapping = [];
        foreach (WorkspaceDocument::TABLES as $t) {
            foreach ($d[$t] as $row) {
                if (DB::table($t)->where('id', $row['id'])->exists()) {
                    $mapping[$row['id']] = (string) Str::uuid();
                }
            }
        }
        $remap = function (array $row) use (&$remap, $mapping): array {
            foreach ($row as $key => &$value) {
                if (in_array($key, ['before_payload', 'after_payload']) && is_array($value)) {
                    $value = $remap($value);
                } elseif (($key === 'id' || str_ends_with($key, '_id')) && is_string($value) && isset($mapping[$value])) {
                    $value = $mapping[$value];
                }
            }
            unset($value);

            return $row;
        };
        foreach (WorkspaceDocument::TABLES as $t) {
            foreach ($d[$t] as $row) {
                $row = $remap($row);
                $row['user_id'] = $user;
                foreach (['created_at', 'updated_at', 'archived_at', 'cancelled_at', 'voided_at', 'legacy_completed_at'] as $timestamp) {
                    if (isset($row[$timestamp])) {
                        $row[$timestamp] = CarbonImmutable::parse($row[$timestamp])->utc()->format('Y-m-d H:i:s');
                    }
                }
                if ($t === 'categories') {
                    $row['name_key'] = (! $row['is_system'] && $row['name']) ? mb_strtolower(trim($row['name'])) : null;
                }
                if ($t === 'operation_revisions') {
                    $row['actor_id'] = $user;
                }
                foreach (['legacy_metadata', 'before_payload', 'after_payload'] as $key) {
                    if (isset($row[$key]) && is_array($row[$key])) {
                        $row[$key] = json_encode($row[$key], JSON_THROW_ON_ERROR);
                    }
                }
                DB::table($t)->insert($row);
            }
        }
        $this->engine->verifyBalances($user, DB::table('accounts')->where('user_id', $user)->pluck('id')->all());
        DB::table('user_settings')->where('user_id', $user)->update($d['settings'] + ['workspace_generation' => $settings->workspace_generation + 1]);

        return ['manifest' => $manifest, 'id_remapping' => $mapping, 'previous_backup_id' => $backup, 'dashboard' => $this->projection->dashboard($user,now($d['settings']['timezone'])->format('Y-m'))];
    }
}
