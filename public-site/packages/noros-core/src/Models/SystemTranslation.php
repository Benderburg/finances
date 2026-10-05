<?php

namespace Noros\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Noros\Core\Support\TranslationLibrary;

/**
 * @property string $key
 * @property string $namespace
 * @property bool $is_custom
 * @property array<string, string|null> $translations
 */
class SystemTranslation extends Model
{
    protected $fillable = ['key', 'translations'];

    protected $attributes = ['is_custom' => true];

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            app(TranslationLibrary::class)->lockForWriting();

            return parent::save($options);
        });
    }

    protected function casts(): array
    {
        return ['translations' => 'array', 'is_custom' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (SystemTranslation $translation): void {
            app(TranslationLibrary::class)->validateKey($translation->key);
            if (! $translation->exists || $translation->isDirty('key')) {
                app(TranslationLibrary::class)->validateStructure([$translation->key]);
            }
            $translation->namespace = explode('.', $translation->key, 2)[0];
        });

        static::saved(function (): void {
            app(TranslationLibrary::class)->clearLoaded();
        });
        static::deleted(function (): void {
            app(TranslationLibrary::class)->clearLoaded();
        });
    }
}
