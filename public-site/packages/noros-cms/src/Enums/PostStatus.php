<?php

namespace Noros\Cms\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('noros-cms::admin.draft'),
            self::Published => __('noros-cms::admin.published_45f5ff'),
            self::Scheduled => __('noros-cms::admin.scheduled'),
            self::Archived => __('noros-cms::admin.archived'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
