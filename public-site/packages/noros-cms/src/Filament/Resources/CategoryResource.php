<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Resources\CategoryResource\Pages;
use Noros\Cms\Models\Category;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.blog');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.category_bbaaec');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.categories');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('noros-cms::admin.name_0918b4'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            Forms\Components\TextInput::make('slug')
                ->label(__('noros-cms::admin.url_identifier'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label(__('noros-cms::admin.description'))
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\ColorPicker::make('color')
                ->label(__('noros-cms::admin.color'))
                ->default('#3763EB'),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('noros-cms::admin.order_f7ca1c'))
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label(__('noros-cms::admin.active_983ec1'))
                ->default(true),
            Forms\Components\TextInput::make('seo_title')
                ->label(__('noros-cms::admin.seo_title'))
                ->maxLength(70),
            Forms\Components\Textarea::make('seo_description')
                ->label(__('noros-cms::admin.ui_meta_description'))
                ->maxLength(170)
                ->rows(3),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ColorColumn::make('color')->label(__('noros-cms::admin.color')),
                Tables\Columns\TextColumn::make('name')->label(__('noros-cms::admin.name_0918b4'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label(__('noros-cms::admin.ui_url'))->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('posts_count')->label(__('noros-cms::admin.articles_count'))->counts('posts')->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('noros-cms::admin.order_f7ca1c'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('noros-cms::admin.active_983ec1'))->boolean(),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
