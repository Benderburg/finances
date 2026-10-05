<?php

namespace Noros\Cms\Enums;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('noros-cms::admin.pending_moderation'),
            self::Approved => __('noros-cms::admin.published'),
            self::Spam => __('noros-cms::admin.spam'),
            self::Rejected => __('noros-cms::admin.rejected'),
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
