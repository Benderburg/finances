<?php

namespace Noros\Cms\Support;

use Filament\Forms\Components\Builder\Block;
use Illuminate\Support\Facades\Lang;

class BlockRegistry
{
    private array $definitions = [];

    private array $templates = [];

    public function register(string $type, string $view, array|\Closure $schema = []): void
    {
        $this->definitions[$type] = compact('view', 'schema');
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function blocks(): array
    {
        $blocks = [];
        foreach ($this->definitions as $type => $definition) {
            if ($definition['schema']) {
                $label = 'noros-cms::blocks.'.$type;
                $blocks[] = Block::make($type)->label(Lang::has($label) ? __($label) : str_replace('_', ' ', ucfirst($type)))->schema($definition['schema'] instanceof \Closure ? ($definition['schema'])() : $definition['schema']);
            }
        }

        return $blocks;
    }

    public function view(string $type): ?string
    {
        return $this->definitions[$type]['view'] ?? null;
    }

    public function registerTemplate(string $key, string $label, string $view): void
    {
        $this->templates[$key] = compact('label', 'view');
    }

    public function templateOptions(): array
    {
        return array_map(fn (array $item): string => $item['label'], $this->templates);
    }

    public function templateView(string $key): ?string
    {
        return $this->templates[$key]['view'] ?? null;
    }

    public function localizedData(array $data, ?string $locale = null): array
    {
        $translated = $data['translations'][$locale ?? app()->getLocale()] ?? [];

        return $this->mergeTranslation($data, is_array($translated) ? $translated : []);
    }

    private function mergeTranslation(array $source, array $translated): array
    {
        foreach ($translated as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $source[$key] = is_array($value) && ! array_is_list($value) && is_array($source[$key] ?? null)
                ? $this->mergeTranslation($source[$key], $value)
                : $value;
        }

        return $source;
    }
}
