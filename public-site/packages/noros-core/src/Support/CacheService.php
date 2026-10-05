<?php

namespace Noros\Core\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CacheService
{
    public int $revision = 0;

    private ?bool $hasVersionTable = null;

    public function remember(string $group, string $key, Closure $loader, ?int $ttl = null): mixed
    {
        // Never publish uncommitted data, or read a pre-transaction snapshot.
        if (DB::connection()->transactionLevel() > 0 || ! $this->hasVersionTable()) {
            return $loader();
        }

        $version = DB::table('noros_cache_versions')->where('group', $group)->value('version') ?? 'initial';
        $cacheKey = 'noros:'.$group.':'.$version.':'.hash('sha256', $key);
        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('value', $cached)) {
                return $cached['value'];
            }
        } catch (Throwable) {
            // Cached data is optional; loader failures are intentionally not caught.
        }

        $value = $loader();
        $ttl ??= (int) config('noros-cache.ttls.'.$group, config('noros-cache.ttl', 300));
        if ($ttl > 0) {
            try {
                Cache::put($cacheKey, ['value' => $value], $ttl);
            } catch (Throwable) {
                // The database remains authoritative when every cache is unavailable.
            }
        }

        return $value;
    }

    public function invalidate(string ...$groups): void
    {
        $this->revision++;
        if (! $this->hasVersionTable()) {
            return;
        }

        // Written on the same DB connection/transaction as the mutation. Rollback
        // rolls back the generation; Redis recovery cannot resurrect old data.
        foreach (array_unique($groups) as $group) {
            DB::table('noros_cache_versions')->upsert(
                [['group' => $group, 'version' => (string) Str::uuid()]], ['group'], ['version'],
            );
        }
    }

    public function status(): array
    {
        $enabled = (bool) config('noros-cache.redis_enabled') || config('cache.default') === 'noros_failover';
        $fallback = config('noros-cache.fallback_store', 'file');
        $result = ['driver' => $enabled ? 'redis' : config('cache.default'), 'status' => 'disabled', 'host' => null, 'port' => null, 'ping_ms' => null];
        if (! $enabled) {
            return $result;
        }

        $connectionName = config('cache.stores.redis.connection', 'cache');
        $connection = config('database.redis.'.$connectionName, []);
        // Never display a raw URL, username, password or exception.
        $url = isset($connection['url']) ? parse_url($connection['url']) : [];
        $host = $url['host'] ?? $connection['host'] ?? '127.0.0.1';
        $result['host'] = is_string($host) && preg_match('/\A[a-z0-9.\-:\[\]]+\z/i', $host) ? $host : '-';
        $port = $url['port'] ?? $connection['port'] ?? 6379;
        $result['port'] = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) ?: null;
        try {
            $start = microtime(true);
            app('redis')->connection($connectionName)->ping();
            $result['ping_ms'] = round((microtime(true) - $start) * 1000, 2);
            $result['status'] = 'connected';
            unset(app(CacheConnectionState::class)->failedStores['redis']);
        } catch (Throwable) {
            app(CacheConnectionState::class)->failedStores['redis'] = true;
            $result['driver'] = $fallback;
            $result['status'] = 'error';
        }

        return $result;
    }

    private function hasVersionTable(): bool
    {
        return $this->hasVersionTable ??= Schema::hasTable('noros_cache_versions');
    }
}
