<?php

namespace Noros\Cms\Enums;

enum PageTemplate: string
{
    case Landing = 'landing';
    case Standard = 'standard';
    case Sidebar = 'sidebar';

    public function label(): string
    {
        return match ($this) {
            self::Landing => __('noros-cms::admin.landing_page_without_heading'),
            self::Standard => __('noros-cms::admin.standard_page'),
            self::Sidebar => __('noros-cms::admin.page_with_right_sidebar'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $template): array => [$template->value => $template->label()])
            ->all();
    }
}
