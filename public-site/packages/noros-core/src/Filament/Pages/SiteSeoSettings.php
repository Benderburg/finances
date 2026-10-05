<?php

namespace Noros\Core\Filament\Pages;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Noros\Core\Support\Settings;
use Noros\Core\Support\SiteSeo;

/** @property-read Schema $form */
class SiteSeoSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected string $view = 'noros-core::filament.pages.site-seo-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('noros-core::seo.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.ui_platform');
    }

    public function mount(): void
    {
        Gate::authorize('manage_settings');
        $this->form->fill(app(SiteSeo::class)->baseSettings());
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make(__('noros-core::seo.defaults'))->schema([
                TextInput::make('site_name')->label(__('noros-core::seo.site_name'))->required()->maxLength(190),
                TextInput::make('default_title')->label(__('noros-core::seo.default_title'))->maxLength(70),
                Textarea::make('default_description')->label(__('noros-core::seo.description'))->maxLength(170),
                TextInput::make('default_image')->label(__('noros-core::seo.image'))->maxLength(2048),
                Select::make('robots')->label('Robots')->options(['index, follow' => 'index, follow', 'noindex, follow' => 'noindex, follow', 'noindex, nofollow' => 'noindex, nofollow'])->required(),
                Select::make('twitter_card')->label('Twitter card')->options(['summary' => 'summary', 'summary_large_image' => 'summary_large_image'])->required(),
                Select::make('x_default_locale')->label('x-default')->options(config('noros.locales'))->required()->in(array_keys(config('noros.locales'))),
                Toggle::make('require_translation')->label(__('noros-core::seo.require_translation')),
                Toggle::make('sitemap_enabled')->label(__('noros-core::seo.sitemap_enabled')),
                Textarea::make('robots_rules')->label(__('noros-core::seo.robots_rules'))->rows(5)->maxLength(10000)
                    ->helperText(__('noros-core::seo.robots_help'))->regex('/\A[\P{C}\r\n\t]*\z/u'),
            ])->columns(2),
            Section::make(__('noros-core::seo.organization'))->schema([
                Toggle::make('organization_enabled')->label(__('noros-core::seo.enabled')),
                Select::make('organization_type')->label(__('noros-core::seo.type'))->options(['Organization' => 'Organization', 'LocalBusiness' => 'LocalBusiness'])->required(),
                TextInput::make('organization_name')->label(__('noros-core::seo.name'))->maxLength(190),
                TextInput::make('organization_url')->label('URL')->rules(['nullable', 'url:http,https'])->maxLength(2048),
                TextInput::make('organization_logo')->label(__('noros-core::seo.logo'))->maxLength(2048),
                TextInput::make('organization_image')->label(__('noros-core::seo.image'))->maxLength(2048),
                Textarea::make('organization_description')->label(__('noros-core::seo.description'))->maxLength(500),
                TextInput::make('organization_phone')->label(__('noros-core::seo.phone'))->maxLength(50),
                TextInput::make('organization_email')->label('Email')->email()->maxLength(190),
                TextInput::make('street_address')->label(__('noros-core::seo.street'))->maxLength(255),
                TextInput::make('address_locality')->label(__('noros-core::seo.locality'))->maxLength(100),
                TextInput::make('address_region')->label(__('noros-core::seo.region'))->maxLength(100),
                TextInput::make('postal_code')->label(__('noros-core::seo.postal_code'))->maxLength(20),
                TextInput::make('address_country')->label(__('noros-core::seo.country'))->nullable()->regex('/\A[A-Z]{2}\z/'),
                TagsInput::make('area_served')->label(__('noros-core::seo.area_served'))->nestedRecursiveRules(['string', 'max:190']),
                TagsInput::make('available_languages')->label(__('noros-core::seo.languages'))->nestedRecursiveRules(['string', 'max:50']),
                TagsInput::make('same_as')->label(__('noros-core::seo.same_as'))->nestedRecursiveRules(['url:http,https', 'max:2048']),
                Repeater::make('opening_hours')->label(__('noros-core::seo.hours'))->maxItems(7)->defaultItems(0)->schema([
                    Select::make('days')->label(__('noros-core::seo.days'))->multiple()->options(array_combine(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']))->required(),
                    TextInput::make('opens')->label(__('noros-core::seo.opens'))->required()->regex('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/'),
                    TextInput::make('closes')->label(__('noros-core::seo.closes'))->required()->regex('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/'),
                ])->columns(3)->columnSpanFull(),
            ])->columns(2),
        ];
        foreach (config('noros.locales') as $locale => $label) {
            $sections[] = Section::make($label)->schema([
                TextInput::make('locales.'.$locale.'.default_title')->label(__('noros-core::seo.default_title'))->maxLength(70),
                Textarea::make('locales.'.$locale.'.default_description')->label(__('noros-core::seo.description'))->maxLength(170),
                TextInput::make('locales.'.$locale.'.og_locale')->label('Open Graph locale')->nullable()->regex('/\A[a-z]{2}_[A-Z]{2}\z/'),
                Textarea::make('locales.'.$locale.'.organization_description')->label(__('noros-core::seo.organization_description'))->maxLength(500),
                TextInput::make('locales.'.$locale.'.street_address')->label(__('noros-core::seo.street'))->maxLength(255),
                TextInput::make('locales.'.$locale.'.address_locality')->label(__('noros-core::seo.locality'))->maxLength(100),
                TagsInput::make('locales.'.$locale.'.area_served')->label(__('noros-core::seo.area_served'))->nestedRecursiveRules(['string', 'max:190']),
            ])->columns(2)->collapsed();
        }

        return $schema->statePath('data')->components($sections);
    }

    public function save(): void
    {
        Gate::authorize('manage_settings');
        $data = $this->form->getState();
        foreach ($data['locales'] ?? [] as $locale => $fields) {
            $data['locales'][$locale] = array_filter($fields, fn (mixed $value): bool => filled($value));
        }
        app(Settings::class)->set('site.seo', $data);
        Notification::make()->title(__('noros-core::seo.saved'))->success()->send();
    }
}
