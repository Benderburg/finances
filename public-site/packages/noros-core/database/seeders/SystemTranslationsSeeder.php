<?php

namespace Noros\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Noros\Core\Models\SystemTranslation;
use Noros\Core\Support\TranslationLibrary;

class SystemTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $sources = ['' => [__DIR__.'/../../lang', lang_path()]];
        foreach (app('translation.loader')->namespaces() as $namespace => $path) {
            if (str_starts_with($namespace, 'noros-')) {
                $sources[$namespace] = [$path];
            }
        }
        $keys = [];
        foreach ($sources as $namespace => $paths) {
            foreach ($paths as $path) {
                foreach (glob($path.'/en/*.php') ?: [] as $file) {
                    $group = ($namespace !== '' ? $namespace.'::' : '').basename($file, '.php');
                    $defaults = require $file;
                    foreach (array_keys(Arr::dot($defaults)) as $key) {
                        $keys[$group.'.'.$key] = $group;
                    }
                }
            }
        }
        DB::transaction(function () use ($keys): void {
            $library = app(TranslationLibrary::class);
            $library->lockForWriting();
            $existing = SystemTranslation::query()->pluck('key')->all();
            $missing = array_diff_key($keys, array_flip($existing));
            foreach (array_keys($missing) as $key) {
                $library->validateKey($key);
            }
            $library->validateStructure(array_keys($missing));
            $rows = [];
            foreach ($missing as $key => $namespace) {
                $rows[] = ['key' => $key, 'namespace' => $namespace, 'translations' => '{}', 'is_custom' => false, 'created_at' => now(), 'updated_at' => now()];
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('system_translations')->insert($chunk);
            }
            $library->clearLoaded();
        });
    }
}
