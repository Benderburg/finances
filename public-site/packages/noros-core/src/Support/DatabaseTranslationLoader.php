<?php

namespace Noros\Core\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;
use Noros\Core\Models\SystemTranslation;

class DatabaseTranslationLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        if ($group === '*' || ! Schema::hasTable('system_translations')) {
            return $lines;
        }

        $prefix = ($namespace && $namespace !== '*' ? $namespace.'::' : '').$group;
        foreach (SystemTranslation::query()->where('namespace', $prefix)->get() as $translation) {
            $value = $translation->translations[$locale] ?? null;
            if ($value !== null && $value !== '') {
                Arr::set($lines, substr($translation->key, strlen($prefix) + 1), $value);
            }
        }

        return $lines;
    }
}
