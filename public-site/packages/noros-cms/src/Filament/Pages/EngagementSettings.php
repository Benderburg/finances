<?php

namespace Noros\Cms\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Noros\Cms\Support\EngagementSettings as Configuration;
use Noros\Core\Support\Settings;

/** @property-read Schema $form */
class EngagementSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected string $view = 'noros-cms::filament.pages.engagement-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::engagement.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('noros-cms::engagement.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        Gate::authorize('manage_settings');
        $this->form->fill(app(Configuration::class)->current());
    }

    public function form(Schema $schema): Schema
    {
        $sections = [Section::make(__('noros-cms::engagement.global'))->schema([
            Toggle::make('global.comments_enabled')->label(__('noros-cms::engagement.comments_enabled')),
            Toggle::make('global.ratings_enabled')->label(__('noros-cms::engagement.ratings_enabled')),
        ])->columns(2)];
        foreach (['blog', 'shop'] as $module) {
            $fields = [];
            foreach (['comments_enabled', 'ratings_enabled'] as $field) {
                $fields[] = Select::make($module.'.'.$field)->label(__('noros-cms::engagement.'.$field))
                    ->options([1 => __('noros-cms::engagement.on'), 0 => __('noros-cms::engagement.off')])->placeholder(__('noros-cms::engagement.inherit'))->nullable();
            }
            foreach (['moderation', 'guest_comments', 'guest_ratings', 'require_login', 'allow_change_vote'] as $field) {
                $fields[] = Toggle::make($module.'.'.$field)->label(__('noros-cms::engagement.'.$field));
            }
            $fields[] = Select::make($module.'.rating_scale')->label(__('noros-cms::engagement.scale'))->options(['stars_5' => __('noros-cms::engagement.stars_5')])->required();
            if ($module === 'shop') {
                $fields[] = Toggle::make('shop.verified_purchase')->label(__('noros-cms::engagement.verified_purchase'));
                $fields[] = Toggle::make('shop.related_enabled')->label(__('noros-cms::engagement.related_enabled'));
                $fields[] = TextInput::make('shop.related_limit')->label(__('noros-cms::engagement.related_limit'))->integer()->minValue(1)->maxValue(24)->required();
            }
            $sections[] = Section::make(__('noros-cms::engagement.'.$module))->schema($fields)->columns(2);
        }

        return $schema->statePath('data')->components($sections);
    }

    public function save(): void
    {
        Gate::authorize('manage_settings');
        app(Settings::class)->set('engagement', app(Configuration::class)->validate($this->form->getState()));
        Notification::make()->title(__('noros-cms::engagement.settings_saved'))->success()->send();
    }
}
