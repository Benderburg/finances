<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Resources\MenuResource\Pages;
use Noros\Cms\Models\Menu;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.website');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.menu');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.menus');
    }

    protected static ?int $navigationSort = 40;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('noros-cms::admin.name_0918b4'))->required()->maxLength(255),
            Forms\Components\Select::make('location')
                ->label(__('noros-cms::admin.location'))
                ->options([
                    'header' => __('noros-cms::admin.header_menu'),
                    'footer_primary' => __('noros-cms::admin.footer_menu_column_1'),
                    'footer_secondary' => __('noros-cms::admin.footer_menu_column_2'),
                ])
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('is_active')->label(__('noros-cms::admin.active_68505b'))->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(__('noros-cms::admin.name_0918b4'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('location')->label(__('noros-cms::admin.location'))->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                'header' => __('noros-cms::admin.header'),
                'footer_primary' => __('noros-cms::admin.footer_column_1'),
                'footer_secondary' => __('noros-cms::admin.footer_column_2'),
                default => $state,
            }),
            Tables\Columns\TextColumn::make('items_count')->label(__('noros-cms::admin.items_count'))->counts('items')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->label(__('noros-cms::admin.active_68505b'))->boolean(),
        ])->recordActions([
            TranslateContent::make(),
            Action::make('items')
                ->label(__('noros-cms::admin.items'))
                ->icon('heroicon-o-list-bullet')
                ->url(fn (Menu $record): string => MenuItemResource::getUrl('index', ['tableFilters' => ['menu' => ['value' => $record->id]]])),
            EditAction::make(),
            DeleteAction::make(),
        ])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
