<?php

namespace Noros\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Noros\Core\Events\PlatformConfigurationChanged;
use Noros\Core\Support\CacheService;
use Noros\Core\Support\PlatformConfiguration;
use Noros\Core\Support\SiteSeo;
use Noros\Core\Support\TranslationLibrary;

/**
 * @property mixed $value
 * @property array<string, mixed>|null $translations
 */
class Setting extends Model
{
    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            app(TranslationLibrary::class)->lockForWriting();

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(CacheService::class)->invalidate('settings', 'cms', 'cms.navigation', 'shop', 'shop.catalog', 'engagement'));
        static::deleted(fn () => app(CacheService::class)->invalidate('settings', 'cms', 'cms.navigation', 'shop', 'shop.catalog', 'engagement'));
        static::saved(fn () => app()->forgetInstance(SiteSeo::class));
        static::deleted(fn () => app()->forgetInstance(SiteSeo::class));
        static::saving(function (Setting $setting): void {
            if ($setting->exists && $setting->getRawOriginal('key') === 'platform' && $setting->isDirty('key')) {
                throw ValidationException::withMessages(['key' => 'The platform configuration key cannot be renamed.']);
            }
            if ($setting->key === 'platform') {
                if (! is_array($setting->value)) {
                    throw ValidationException::withMessages(['value' => 'Platform configuration must be an object.']);
                }
                $setting->value = app(PlatformConfiguration::class)->validate($setting->value);
            }
        });
        static::deleting(function (Setting $setting): void {
            if ($setting->key === 'platform') {
                throw ValidationException::withMessages(['key' => 'Edit platform configuration instead of deleting it.']);
            }
        });
        static::saved(function (Setting $setting): void {
            if ($setting->key !== 'platform' || (! $setting->wasRecentlyCreated && ! $setting->wasChanged('value'))) {
                return;
            }
            $previous = ['enabled_locales' => array_keys(config('noros.locales')), 'default_locale' => config('noros.default_locale'), 'fallback_locale' => config('noros.fallback_locale'), 'timezone' => config('app.timezone')];
            $configuration = app(PlatformConfiguration::class);
            $configuration->apply($setting->value);
            try {
                event(new PlatformConfigurationChanged($setting->value));
            } finally {
                $configuration->apply($previous);
            }
        });
    }

    protected $fillable = ['key', 'value', 'translations'];

    protected function casts(): array
    {
        return ['value' => 'json', 'translations' => 'array'];
    }
}
