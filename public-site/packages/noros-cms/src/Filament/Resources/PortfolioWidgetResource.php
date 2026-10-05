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
use Noros\Cms\Filament\Resources\PortfolioWidgetResource\Pages;
use Noros\Cms\Models\PortfolioWidget;

class PortfolioWidgetResource extends Resource
{
    protected static ?string $model = PortfolioWidget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.website');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.portfolio_widget_980bb6');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.portfolio_widgets');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Schema $form): Schema
    {
        return $form->columns(1)->schema([
            Section::make(__('noros-cms::admin.block_appearance'))->schema([
                Forms\Components\TextInput::make('name')->label(__('noros-cms::admin.name_in_administration'))->required()->maxLength(255),
                Forms\Components\Toggle::make('is_active')->label(__('noros-cms::admin.active'))->default(true),
                Forms\Components\TextInput::make('eyebrow')->label(__('noros-cms::admin.eyebrow'))->maxLength(120),
                Forms\Components\TextInput::make('title')->label(__('noros-cms::admin.title'))->required()->maxLength(180),
                Forms\Components\Textarea::make('description')->label(__('noros-cms::admin.description'))->rows(3)->columnSpanFull(),
            ])->columns(2),
            Section::make(__('noros-cms::admin.projects'))->schema([
                Forms\Components\Repeater::make('projects')
                    ->label(__('noros-cms::admin.project_cards'))
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label(__('noros-cms::admin.image'))
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('portfolio/widgets')
                            ->visibility('public')
                            ->required(),
                        Forms\Components\TextInput::make('image_alt')->label('Alt')->maxLength(255),
                        Forms\Components\TextInput::make('title')->label(__('noros-cms::admin.name_0918b4'))->required()->maxLength(180),
                        Forms\Components\Textarea::make('description')->label(__('noros-cms::admin.description'))->rows(3),
                        Forms\Components\TextInput::make('url')->label(__('noros-cms::admin.project_url'))->required()->maxLength(255),
                        Forms\Components\TextInput::make('filter')->label(__('noros-cms::admin.filter_class'))->placeholder('landing graphic')->maxLength(120),
                        Forms\Components\Toggle::make('open_new_tab')->label(__('noros-cms::admin.open_in_a_new_tab'))->default(false),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->cloneable()
                    ->reorderableWithButtons()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(__('noros-cms::admin.name_0918b4'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label(__('noros-cms::admin.title'))->toggleable(),
            Tables\Columns\TextColumn::make('projects')->label(__('noros-cms::admin.projects_count'))->formatStateUsing(fn (?array $state): int => count($state ?? [])),
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
            'index' => Pages\ListPortfolioWidgets::route('/'),
            'create' => Pages\CreatePortfolioWidget::route('/create'),
            'edit' => Pages\EditPortfolioWidget::route('/{record}/edit'),
        ];
    }
}
