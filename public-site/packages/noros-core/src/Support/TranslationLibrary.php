<?php

namespace Noros\Core\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Noros\Core\Models\SystemTranslation;
use stdClass;

class TranslationLibrary
{
    public const KEY_PATTERN = '/\A(?:[a-z][a-z0-9_-]*::)?[a-z][a-z0-9_-]*\.[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\z/';

    private array $loadedGroups = [];

    public function lockForWriting(): void
    {
        // The no-op upsert holds a write lock until the surrounding transaction ends.
        DB::table('translation_write_locks')->upsert([['name' => 'library']], ['name'], ['name']);
        $this->clearLoaded();
    }

    public function clearLoaded(): void
    {
        $this->loadedGroups = [];
        app('translator')->setLoaded([]);
    }

    /** @param list<string> $keys */
    public function validateStructure(array $keys): void
    {
        $allKeys = array_fill_keys([...SystemTranslation::query()->pluck('key')->all(), ...$keys], true);
        $parents = [];
        foreach (array_keys($allKeys) as $existing) {
            $prefix = $existing;
            while (str_contains($prefix, '.')) {
                $prefix = substr($prefix, 0, strrpos($prefix, '.'));
                $parents[$prefix] = true;
            }
        }
        foreach (array_keys($allKeys) as $key) {
            if (isset($parents[$key])) {
                throw ValidationException::withMessages(['key' => 'Translation keys must not contain another translation key as a parent.']);
            }
        }

        foreach ($keys as $key) {
            [$group, $item] = explode('.', $key, 2);
            foreach (array_keys(config('noros.locales')) as $locale) {
                $node = $this->loadGroup($group, $locale);
                foreach (explode('.', $item) as $segment) {
                    if (! is_array($node)) {
                        throw ValidationException::withMessages(['key' => 'Translation key conflicts with a language file value.']);
                    }
                    if (! array_key_exists($segment, $node)) {
                        $node = null;
                        break;
                    }
                    $node = $node[$segment];
                }
                if (is_array($node)) {
                    throw ValidationException::withMessages(['key' => 'Translation key conflicts with a language file group.']);
                }
            }
        }
    }

    private function loadGroup(string $group, string $locale): array
    {
        $cacheKey = $locale.'|'.$group;
        if (! array_key_exists($cacheKey, $this->loadedGroups)) {
            $namespace = null;
            if (str_contains($group, '::')) {
                [$namespace, $group] = explode('::', $group, 2);
            }
            $this->loadedGroups[$cacheKey] = app('translation.loader')->load($locale, $group, $namespace);
        }

        return $this->loadedGroups[$cacheKey];
    }

    public function validateKey(string $key): void
    {
        if (strlen($key) > 190 || strlen(explode('.', $key, 2)[0]) > 100
            || ! preg_match(self::KEY_PATTERN, $key)) {
            throw ValidationException::withMessages(['key' => 'Use namespace.key (maximum 190 bytes; namespace 100 bytes).']);
        }
    }

    public function value(string $key, string $locale): ?string
    {
        [$group, $item] = explode('.', $key, 2);
        $lines = $this->loadGroup($group, $locale);
        $value = Arr::get($lines, $item);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    public function missingKeys(string $locale): array
    {
        return SystemTranslation::query()->pluck('key')->filter(fn (string $key): bool => $this->value($key, $locale) === null)->values()->all();
    }

    /** @return array<string, array<string, string|null>> */
    public function parse(string $json): array
    {
        if (strlen($json) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['json' => 'Import must not exceed 2 MiB.']);
        }

        try {
            $decoded = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['json' => 'Invalid JSON document.']);
        }

        if (! $decoded instanceof stdClass || count(get_object_vars($decoded)) > 10000) {
            throw ValidationException::withMessages(['json' => 'Expected an object with at most 10,000 keys.']);
        }

        $result = [];
        $locales = config('noros.locales');
        foreach (get_object_vars($decoded) as $key => $values) {
            if (! is_string($key) || ! $values instanceof stdClass) {
                throw ValidationException::withMessages(['json' => 'Invalid translation key or locale object.']);
            }
            try {
                $this->validateKey($key);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['json' => 'Invalid translation key.']);
            }

            $result[$key] = [];
            foreach (get_object_vars($values) as $locale => $value) {
                if (! array_key_exists($locale, $locales) || (! is_string($value) && $value !== null)
                    || (is_string($value) && mb_strlen($value) > 10000)) {
                    throw ValidationException::withMessages(['json' => 'Unknown locale or invalid translation value.']);
                }
                $result[$key][$locale] = $value;
            }
        }

        return $result;
    }

    /** @return array{created: int, updated: int} */
    public function import(string $json, bool $dryRun = true): array
    {
        $data = $this->parse($json);
        $this->clearLoaded();

        $result = DB::transaction(function () use ($data, $dryRun): array {
            if (! $dryRun) {
                $this->lockForWriting();
            }
            $this->validateStructure(array_keys($data));
            $records = SystemTranslation::query()->whereIn('key', array_keys($data))->lockForUpdate()->get()->keyBy('key');
            $result = ['created' => 0, 'updated' => 0];
            $rows = [];
            foreach ($data as $key => $values) {
                $record = $records->get($key);
                $result[$record ? 'updated' : 'created']++;
                if (! $dryRun) {
                    $rows[] = [
                        'key' => $key,
                        'namespace' => explode('.', $key, 2)[0],
                        'translations' => json_encode(array_replace($record->translations ?? [], $values), JSON_THROW_ON_ERROR),
                        'is_custom' => $record->is_custom ?? true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                SystemTranslation::upsert($chunk, ['key'], ['translations', 'updated_at']);
            }

            return $result;
        });
        $this->clearLoaded();

        return $result;
    }

    /** @param list<string> $namespaces */
    public function export(?string $locale = null, array $namespaces = []): string
    {
        $this->clearLoaded();
        if ($locale !== null && ! array_key_exists($locale, config('noros.locales'))) {
            throw ValidationException::withMessages(['locale' => 'Unknown locale.']);
        }

        $data = new stdClass;
        foreach (SystemTranslation::query()->when($namespaces, fn ($query) => $query->whereIn('namespace', $namespaces))->orderBy('key')->cursor() as $record) {
            $values = [];
            foreach ($locale === null ? array_keys(config('noros.locales')) : [$locale] as $language) {
                $values[$language] = $this->value($record->key, $language);
            }
            $data->{$record->key} = (object) $values;
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
