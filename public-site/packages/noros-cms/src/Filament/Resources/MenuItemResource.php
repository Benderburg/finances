<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Resources\MenuItemResource\Pages;
use Noros\Cms\Models\MenuItem;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\Post;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.website');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.menu_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.menu_items');
    }

    protected static ?int $navigationSort = 50;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('menu_id')
                ->label(__('noros-cms::admin.menus'))
                ->relationship('menu', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live(),
            Forms\Components\Select::make('parent_id')
                ->label(__('noros-cms::admin.parent_item'))
                ->options(fn (Get $get, ?MenuItem $record): array => MenuItem::query()
                    ->where('menu_id', $get('menu_id'))
                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->orderBy('sort_order')
                    ->pluck('label', 'id')
                    ->all())
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('label')->label(__('noros-cms::admin.link_text'))->required()->maxLength(120),
            Forms\Components\Select::make('type')
                ->label(__('noros-cms::admin.link_type'))
                ->options([
                    'page' => __('noros-cms::admin.existing_page'),
                    'blog_index' => __('noros-cms::admin.blog'),
                    'blog_post' => __('noros-cms::admin.blog_article'),
                    'custom' => __('noros-cms::admin.custom_link'),
                ])
                ->default('page')
                ->required()
                ->live(),
            Forms\Components\Select::make('page_id')
                ->label(__('noros-cms::admin.page'))
                ->options(fn (): array => Page::query()->orderBy('path')->pluck('title', 'id')->all())
                ->required(fn (Get $get): bool => $get('type') === 'page')
                ->visible(fn (Get $get): bool => $get('type') === 'page')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('post_id')
                ->label(__('noros-cms::admin.article'))
                ->options(fn (): array => Post::query()->orderByDesc('published_at')->pluck('title', 'id')->all())
                ->required(fn (Get $get): bool => $get('type') === 'blog_post')
                ->visible(fn (Get $get): bool => $get('type') === 'blog_post')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('url')
                ->label(__('noros-cms::admin.ui_url'))
                ->placeholder(__('noros-cms::admin.contacts_or_https_example_com'))
                ->required(fn (Get $get): bool => $get('type') === 'custom')
                ->visible(fn (Get $get): bool => $get('type') === 'custom')
                ->maxLength(255),
            Forms\Components\Select::make('target')
                ->label(__('noros-cms::admin.open_links'))
                ->options(['_self' => __('noros-cms::admin.in_the_current_tab'), '_blank' => __('noros-cms::admin.in_a_new_tab')])
                ->default('_self')
                ->required(),
            Forms\Components\TextInput::make('css_class')->label(__('noros-cms::admin.additional_css_classes'))->maxLength(255),
            Forms\Components\TextInput::make('sort_order')->label(__('noros-cms::admin.order_f7ca1c'))->numeric()->default(0)->minValue(0)->required(),
            Forms\Components\Toggle::make('is_active')->label(__('noros-cms::admin.active'))->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('menu.name')->label(__('noros-cms::admin.menus'))->badge()->sortable(),
                Tables\Columns\TextColumn::make('label')->label(__('noros-cms::admin.item'))->searchable()->sortable()->description(fn (MenuItem $record): string => $record->resolved_url),
                Tables\Columns\TextColumn::make('parent.label')->label(__('noros-cms::admin.parent'))->placeholder('—'),
                Tables\Columns\TextColumn::make('type')->label(__('noros-cms::admin.type'))->badge(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('noros-cms::admin.order_f7ca1c'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('noros-cms::admin.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('menu')->label(__('noros-cms::admin.menus'))->relationship('menu', 'name'),
            ])
            ->recordActions([
                TranslateContent::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
