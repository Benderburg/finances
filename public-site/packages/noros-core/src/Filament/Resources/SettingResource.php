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
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Noros\Core\Models\Setting;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        if ($record?->getAttribute('key') === 'platform' && in_array($action, ['delete', 'forceDelete'], true)) {
            return Response::deny();
        }

        return Gate::inspect('manage_settings');
    }

    public static function getModelLabel(): string
    {
        return __('noros-core::admin.ui_setting');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-core::admin.ui_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.ui_platform');
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [
            TextInput::make('key')->label(__('noros-core::admin.ui_key'))->required()->maxLength(190)->regex('/\\A[a-zA-Z][a-zA-Z0-9_.-]*\\z/')->unique(ignoreRecord: true),
            Textarea::make('value')->label(__('noros-core::admin.ui_value_json'))->required()->rules(['json'])->maxLength(65535)
                ->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                ->dehydrateStateUsing(fn (string $state): mixed => json_decode($state, true, 512, JSON_THROW_ON_ERROR)),
        ];
        foreach (config('noros.locales') as $locale => $label) {
            $fields[] = Textarea::make('translations.'.$locale)->label($label)->maxLength(65535)->helperText('Text or JSON object / array')
                ->rules(fn (?string $state): array => preg_match('/\A\s*[\[{]/', $state ?? '') ? ['nullable', 'json'] : ['nullable', 'string'])
                ->formatStateUsing(fn (mixed $state): ?string => is_array($state) ? json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $state)
                ->dehydrateStateUsing(fn (?string $state): mixed => preg_match('/\A\s*[\[{]/', $state ?? '') ? json_decode($state, true, 512, JSON_THROW_ON_ERROR) : $state);
        }

        return $schema->columns(1)->components($fields);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->label(__('noros-core::admin.ui_key'))->searchable()->sortable(),
            TextColumn::make('updated_at')->label(__('noros-core::admin.ui_updated_at'))->dateTime(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => SettingResource\Pages\ManageSettings::route('/')];
    }
}
