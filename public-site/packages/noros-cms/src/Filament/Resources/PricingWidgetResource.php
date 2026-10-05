<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Resources\PricingWidgetResource\Pages;
use Noros\Cms\Models\PricingWidget;

class PricingWidgetResource extends Resource
{
    protected static ?string $model = PricingWidget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.website');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.pricing_widget_4531f0');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.pricing_widgets');
    }

    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form->columns(1)->schema([
            Section::make(__('noros-cms::admin.block_appearance'))->schema([
                Forms\Components\TextInput::make('name')->label(__('noros-cms::admin.name_in_administration'))->required()->maxLength(255),
                Forms\Components\Toggle::make('is_active')->label(__('noros-cms::admin.active'))->default(true),
                Forms\Components\TextInput::make('eyebrow')->label(__('noros-cms::admin.eyebrow'))->maxLength(120),
                Forms\Components\TextInput::make('title')->label(__('noros-cms::admin.title'))->required()->maxLength(180),
                Forms\Components\Textarea::make('description')->label(__('noros-cms::admin.description'))->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('currency')->label(__('noros-cms::admin.currency'))->default('€')->required()->maxLength(10),
            ])->columns(2),
            Section::make(__('noros-cms::admin.pricing_plans'))->schema([
                Forms\Components\Repeater::make('plans')
                    ->label(__('noros-cms::admin.pricing_cards'))
                    ->schema([
                        Forms\Components\TextInput::make('name')->label(__('noros-cms::admin.name_0918b4'))->required()->maxLength(120),
                        Forms\Components\TextInput::make('subtitle')->label(__('noros-cms::admin.subtitle'))->maxLength(180),
                        Forms\Components\TextInput::make('price')->label(__('noros-cms::admin.price'))->required()->numeric()->minValue(0),
                        Forms\Components\TextInput::make('price_prefix')->label(__('noros-cms::admin.prefix'))->placeholder(__('noros-cms::admin.from'))->maxLength(30),
                        Forms\Components\TextInput::make('price_suffix')->label(__('noros-cms::admin.suffix'))->placeholder(__('noros-cms::admin.month'))->maxLength(30),
                        Forms\Components\TextInput::make('timeline')->label(__('noros-cms::admin.timeline'))->maxLength(120),
                        Forms\Components\TagsInput::make('features')->label(__('noros-cms::admin.included_features'))->columnSpanFull(),
                        Forms\Components\TextInput::make('button_text')->label(__('noros-cms::admin.button_text'))->default(__('noros-cms::admin.order'))->maxLength(80),
                        Forms\Components\TextInput::make('button_url')->label(__('noros-cms::admin.link'))->default('/contacts')->maxLength(255),
                        Forms\Components\Toggle::make('featured')->label(__('noros-cms::admin.feature_this_plan'))->default(false),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->cloneable()
                    ->reorderableWithButtons()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make(__('noros-cms::admin.one_time_tasks'))->schema([
                Forms\Components\Repeater::make('tasks')
                    ->label(__('noros-cms::admin.table_rows'))
                    ->schema([
                        Forms\Components\TextInput::make('name')->label(__('noros-cms::admin.task'))->required(),
                        Forms\Components\TextInput::make('timeline')->label(__('noros-cms::admin.timeline'))->maxLength(120),
                        Forms\Components\TextInput::make('price')->label(__('noros-cms::admin.price'))->numeric()->minValue(0),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderableWithButtons()
                    ->columnSpanFull(),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(__('noros-cms::admin.name_0918b4'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label(__('noros-cms::admin.title'))->toggleable(),
            Tables\Columns\TextColumn::make('plans')->label(__('noros-cms::admin.plans_count'))->formatStateUsing(fn (?array $state): int => count($state ?? [])),
            Tables\Columns\IconColumn::make('is_active')->label(__('noros-cms::admin.active'))->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->label(__('noros-cms::admin.updated'))->since()->sortable(),
        ])->recordActions([
            TranslateContent::make(),
            EditAction::make(),
            DeleteAction::make(),
        ])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPricingWidgets::route('/'),
            'create' => Pages\CreatePricingWidget::route('/create'),
            'edit' => Pages\EditPricingWidget::route('/{record}/edit'),
        ];
    }
}
