<?php

namespace Noros\Core\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Noros\Core\Models\Setting;

class Settings
{
    private ?array $values = null;

    private int $revision = -1;

    public function get(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $cache = app(CacheService::class);
        $inTransaction = DB::connection()->transactionLevel() > 0;
        if ($inTransaction || $this->values === null || $this->revision !== $cache->revision) {
            $values = $cache->remember('settings', 'all', function (): array {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->get(['key', 'value', 'translations'])->keyBy('key')->toArray();
            });
            if (! $inTransaction) {
                $this->values = $values;
                $this->revision = $cache->revision;
            }
        } else {
            $values = $this->values;
        }
        $setting = $values[$key] ?? null;
        if ($setting === null) {
            return $default;
        }
        $locale ??= app()->getLocale();

        return $setting['translations'][$locale] ?? $setting['translations'][config('noros.fallback_locale')] ?? $setting['value'] ?? $default;
    }

    public function set(string $key, mixed $value, array $translations = []): Setting
    {
        return Setting::query()->updateOrCreate(['key' => $key], compact('value', 'translations'));
    }
}
