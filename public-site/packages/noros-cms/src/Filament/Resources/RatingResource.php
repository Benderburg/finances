<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Noros\Cms\Filament\CmsResource;
use Noros\Cms\Models\Rating;

class RatingResource extends CmsResource
{
    protected static ?string $model = Rating::class;

    protected static string|\UnitEnum|null $navigationGroup = 'CMS';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.ui_rating');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.ui_ratings');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'CMS';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('rateable_type')->label(__('noros-cms::admin.content_type'))->disabled()->dehydrated(false),
            TextInput::make('rateable_id')->label(__('noros-cms::admin.content_id'))->disabled()->dehydrated(false),
            TextInput::make('score')->label(__('noros-cms::admin.rating'))->integer()->minValue(1)->maxValue(5)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('rateable_type')->label(__('noros-cms::admin.content_type'))->searchable(),
            TextColumn::make('rateable_id')->label(__('noros-cms::admin.content_id'))->sortable(),
            TextColumn::make('score')->label(__('noros-cms::admin.rating'))->sortable(),
            TextColumn::make('created_at')->label(__('noros-cms::admin.created_at'))->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => RatingResource\Pages\ManageRatings::route('/')];
    }
}
