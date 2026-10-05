<?php

namespace Noros\Core\Filament\Resources;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Noros\Core\Filament\Resources\SystemTranslationResource\Pages;
use Noros\Core\Models\SystemTranslation;
use Noros\Core\Support\TranslationLibrary;

class SystemTranslationResource extends Resource
{
    protected static ?string $model = SystemTranslation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    public static function getNavigationLabel(): string
    {
        return __('noros-core::admin.ui_translations');
    }

    public static function getModelLabel(): string
    {
        return __('noros-core::admin.ui_system_translation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-core::admin.ui_translations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.ui_platform');
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [TextInput::make('key')->label(__('noros-core::admin.ui_key'))->required()->maxLength(190)
            ->regex(TranslationLibrary::KEY_PATTERN)
            ->unique(ignoreRecord: true)->disabledOn('edit')->columnSpanFull()];

        foreach (config('noros.locales') as $locale => $label) {
            $fields[] = Textarea::make('translations.'.$locale)->label($label)
                ->maxLength(10000)->rows(4)
                ->helperText(__('noros-core::admin.ui_leave_empty_to_use_the_language_file_or_fallback'));
        }

        return $schema->columns(1)->components($fields);
    }

    public static function table(Table $table): Table
    {
        $columns = [TextColumn::make('key')->label(__('noros-core::admin.ui_key'))->searchable()->sortable(), TextColumn::make('namespace')->label(__('noros-core::admin.ui_namespace'))->searchable()->sortable()];
        foreach (config('noros.locales') as $locale => $label) {
            $columns[] = TextColumn::make('translations.'.$locale)->label($label)
                ->getStateUsing(fn (SystemTranslation $record): ?string => app(TranslationLibrary::class)->value($record->key, $locale))
                ->placeholder(__('noros-core::admin.ui_missing_translation'))->limit(60);
        }

        return $table->columns($columns)->filters([
            SelectFilter::make('namespace')->options(fn (): array => SystemTranslation::query()->distinct()->orderBy('namespace')->pluck('namespace', 'namespace')->all()),
            SelectFilter::make('missing_locale')->label(__('noros-core::admin.ui_missing_translation'))->options(config('noros.locales'))
                ->query(function (Builder $query, array $data): Builder {
                    $locale = $data['value'] ?? null;
                    if (! is_string($locale) || ! array_key_exists($locale, config('noros.locales'))) {
                        return $query;
                    }

                    return $query->whereIn('key', app(TranslationLibrary::class)->missingKeys($locale));
                }),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTranslations::route('/')];
    }
}
