<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CommandBus
{
    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    public function execute(string $userId, string $key, string $command, array $payload, callable $work): array
    {
        if (! Str::isUuid($key)) {
            throw new DomainError('INVALID_COMMAND_KEY');
        }
        $hash = hash('sha256', json_encode(self::canonical([$command, $payload]), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($userId, $key, $hash, $work) {
            // No financial reads before this lock: it serializes the owner's entire workspace.
            $settings = DB::table('user_settings')->where('user_id', $userId)->lockForUpdate()->first();
            if (! $settings) {
                throw new DomainError('WORKSPACE_NOT_INITIALIZED', 409);
            }
            $existing = DB::table('command_deduplication')->where('user_id', $userId)->where('command_key', $key)->first();
            if ($existing) {
                if ($existing->workspace_generation != $settings->workspace_generation) {
                    throw new DomainError('WORKSPACE_REPLACED', 409);
                }
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new DomainError('IDEMPOTENCY_CONFLICT', 409);
                }
                $result = json_decode($existing->response, true, 512, JSON_THROW_ON_ERROR);
                $result['meta']['replayed'] = true;

                return $result;
            }
            $data = $work($settings);
            DB::table('user_settings')->where('user_id', $userId)->increment('workspace_revision');
            $generation = DB::table('user_settings')->where('user_id', $userId)->value('workspace_generation');
            $result = ['data' => $data, 'meta' => ['workspace_revision' => (int) $settings->workspace_revision + 1, 'replayed' => false]];
            DB::table('command_deduplication')->insert(['user_id' => $userId, 'command_key' => $key, 'workspace_generation' => $generation, 'request_hash' => $hash, 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $result;
        }, 3);
    }

    public function snapshot(string $userId, callable $read): array
    {
        return DB::transaction(function () use ($userId, $read) {
            $settings = DB::table('user_settings')->where('user_id', $userId)->sharedLock()->first();

            return ['data' => $read($settings), 'meta' => ['workspace_revision' => (int) $settings->workspace_revision]];
        }, 3);
    }
}
