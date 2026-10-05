<?php

namespace Noros\Cms\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
use Noros\Cms\Filament\Forms\StructuredDataField;
use Noros\Cms\Models\TranslatableModel as Model;
use Noros\Cms\Support\LocalizedContent;

class TranslateContent
{
    public static function make(): Action
    {
        return Action::make('translate')->label('Translations')
            ->fillForm(fn (Model $record): array => ['locale' => config('noros.default_locale'), 'fields' => self::formFields($record, config('noros.default_locale'))])
            ->schema(function (Model $record): array {
                $schema = [
                    Select::make('locale')->options(config('noros.locales'))->required()->live()->afterStateUpdated(function ($state, $set, Model $record): void {
                        $set('fields', self::formFields($record, $state));
                    }),
                    Text::make(fn (Get $get): string => __('cms.translations.'.$record->translationStatus($get('locale') ?: config('noros.default_locale')))),
                ];
                foreach ($record->translatableFields() as $field) {
                    if ($field === 'structured_data') {
                        $schema[] = StructuredDataField::make('fields.'.$field);

                        continue;
                    }
                    $component = Textarea::make('fields.'.$field)->label(str_replace('_', ' ', ucfirst($field)))->rows(in_array($field, ['content', 'blocks'], true) ? 10 : 2)->maxLength(1000000);
                    if ($field === 'canonical_url') {
                        $component->rules(['nullable', 'url:http,https']);
                    }
                    if ($record->hasCast($field, ['array', 'json'])) {
                        $component->helperText('JSON')->rules(['nullable', 'json', function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                if (filled($value)) {
                                    try {
                                        $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
                                    } catch (\JsonException) {
                                        $fail('Invalid JSON or nesting exceeds 32 levels.');

                                        return;
                                    }
                                    if (! is_array($decoded)) {
                                        $fail('Expected a JSON array or object.');
                                    }
                                }
                            };
                        }])->dehydrateStateUsing(fn (?string $state): mixed => filled($state) ? json_decode($state, true, 32, JSON_THROW_ON_ERROR) : null);
                    }
                    $schema[] = $component;
                }

                return $schema;
            })
            ->action(function (Model $record, array $data): void {
                $feature = match ($record->getTable()) {
                    'posts', 'categories', 'tags' => 'blog', 'portfolio_projects', 'portfolio_widgets' => 'portfolio', 'menus', 'menu_items' => 'menus', default => 'pages'
                };
                Gate::authorize('manage_'.$feature);
                app(LocalizedContent::class)->save($record, $data['locale'], $data['fields']);
            });
    }

    private static function formFields(Model $record, string $locale): array
    {
        $fields = app(LocalizedContent::class)->fields($record, $locale);
        foreach ($fields as $key => $value) {
            if (is_array($value) && $key !== 'structured_data') {
                $fields[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        return $fields;
    }
}
