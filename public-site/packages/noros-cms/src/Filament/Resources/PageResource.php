<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Builder\Block;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Noros\Cms\Enums\PageStatus;
use Noros\Cms\Enums\PageTemplate;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Forms\StructuredDataField;
use Noros\Cms\Filament\Resources\PageResource\Pages;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioWidget;
use Noros\Cms\Models\PricingWidget;
use Noros\Cms\Support\BlockRegistry;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-window';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.website');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.page_2db155');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.pages');
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $form): Schema
    {
        return $form->columns(1)->schema([
            Section::make(__('noros-cms::admin.page'))
                ->schema([
                    Forms\Components\TextInput::make('heading')->label(__('noros-cms::admin.banner_heading'))->maxLength(255),
                    Forms\Components\TextInput::make('title')
                        ->label(__('noros-cms::admin.title'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('noros-cms::admin.url_segment'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(255)
                        ->helperText(__('noros-cms::admin.the_full_address_is_built_from_the_parent_page_automatically')),
                    Forms\Components\Select::make('parent_id')
                        ->label(__('noros-cms::admin.parent_page'))
                        ->options(fn (?Page $record): array => Page::query()
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->orderBy('path')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\Select::make('template')
                        ->label(__('noros-cms::admin.page_template'))
                        ->options(PageTemplate::options())
                        ->default(PageTemplate::Standard->value)
                        ->required()
                        ->live(),
                    Forms\Components\Toggle::make('is_home')
                        ->label(__('noros-cms::admin.home_page'))
                        ->helperText(__('noros-cms::admin.the_home_page_does_not_display_a_parent_or_url_segment'))
                        ->default(false),
                ])
                ->columns(2),

            Section::make(__('noros-cms::admin.publication'))
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label(__('noros-cms::admin.status'))
                        ->options(PageStatus::options())
                        ->default(PageStatus::Draft->value)
                        ->required(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label(__('noros-cms::admin.publication_date'))
                        ->seconds(false)
                        ->helperText(__('noros-cms::admin.choose_a_future_date_for_a_scheduled_page')),
                ])
                ->columns(2),

            Section::make(__('noros-cms::admin.page_builder'))
                ->description(__('noros-cms::admin.blocks_appear_in_the_specified_order_add_a_hero_using_a_regular_b'))
                ->schema([
                    Forms\Components\Builder::make('blocks')
                        ->label(__('noros-cms::admin.blocks'))
                        ->blocks([
                            ...array_map(fn (Block $block): Block => $block->schema([...$block->getDefaultChildComponents(), Forms\Components\Hidden::make('enabled')->default(true)]), static::pageBlocks()),
                            ...app(BlockRegistry::class)->blocks(),
                        ])
                        ->blockNumbers(false)
                        ->cloneable()
                        ->extraItemActions([
                            Action::make('toggleEnabled')->label(__('noros-cms::blocks.toggle'))->icon('heroicon-o-eye-slash')
                                ->action(function (array $arguments, Forms\Components\Builder $component): void {
                                    $items = $component->getRawState();
                                    $key = $arguments['item'] ?? null;
                                    if ($key !== null && isset($items[$key])) {
                                        $items[$key]['data']['enabled'] = ! ($items[$key]['data']['enabled'] ?? true);
                                        $component->rawState($items);
                                        $component->callAfterStateUpdated();
                                    }
                                }),
                        ])
                        ->collapsible()
                        ->reorderableWithButtons()
                        ->addActionLabel(__('noros-cms::admin.add_block'))
                        ->columnSpanFull(),
                ]),

            Section::make(__('noros-cms::admin.right_sidebar'))
                ->description(__('noros-cms::admin.used_by_the_right_sidebar_template_above_the_standard_blog_sideba'))
                ->visible(fn (Get $get): bool => $get('template') === PageTemplate::Sidebar->value)
                ->schema([
                    Forms\Components\RichEditor::make('sidebar_content')
                        ->label(__('noros-cms::admin.right_sidebar_content'))
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages/sidebar')
                        ->columnSpanFull(),
                ]),

            Section::make(__('noros-cms::admin.seo_and_social_media'))
                ->schema([
                    Forms\Components\TextInput::make('seo_title')->label(__('noros-cms::admin.seo_title'))->maxLength(70),
                    Forms\Components\TextInput::make('canonical_url')->label(__('noros-cms::admin.ui_canonical_url'))->rules(['nullable', 'url:http,https'])->maxLength(255),
                    Forms\Components\Textarea::make('seo_description')->label(__('noros-cms::admin.ui_meta_description'))->rows(3)->maxLength(170),
                    Forms\Components\Textarea::make('seo_keywords')->label(__('noros-cms::admin.keywords'))->rows(3),
                    Forms\Components\Select::make('seo_robots')
                        ->label(__('noros-cms::admin.ui_robots'))
                        ->options([
                            'index,follow' => 'index, follow',
                            'index,nofollow' => 'index, nofollow',
                            'noindex,follow' => 'noindex, follow',
                            'noindex,nofollow' => 'noindex, nofollow',
                        ])
                        ->default('index,follow')
                        ->required(),
                    Forms\Components\TextInput::make('og_type')->label(__('noros-cms::admin.ui_open_graph_type'))->default('website')->maxLength(40),
                    Forms\Components\TextInput::make('og_title')->label(__('noros-cms::admin.open_graph_title'))->maxLength(95),
                    Forms\Components\Textarea::make('og_description')->label(__('noros-cms::admin.open_graph_description'))->rows(3)->maxLength(220),
                    Forms\Components\FileUpload::make('og_image')
                        ->label(__('noros-cms::admin.open_graph_image'))
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('pages/social')
                        ->visibility('public'),
                    Forms\Components\Select::make('twitter_card')
                        ->label(__('noros-cms::admin.ui_twitter_card'))
                        ->options([
                            'summary_large_image' => __('noros-cms::admin.large_image'),
                            'summary' => __('noros-cms::admin.compact_card'),
                        ])
                        ->default('summary_large_image'),
                    StructuredDataField::make(),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }

    /** @return array<int, Block> */
    public static function pageBlocks(): array
    {
        $buttonFields = fn (): array => [
            Forms\Components\TextInput::make('button_text')->label(__('noros-cms::admin.button_text'))->maxLength(80),
            Forms\Components\TextInput::make('button_url')->label(__('noros-cms::admin.button_url'))->maxLength(255),
        ];

        $baseHeroFields = fn (): array => [
            Forms\Components\TextInput::make('eyebrow')->label(__('noros-cms::admin.eyebrow'))->maxLength(120),
            Forms\Components\TextInput::make('title')->label(__('noros-cms::admin.title'))->required()->maxLength(180),
            Forms\Components\Textarea::make('description')->label(__('noros-cms::admin.description'))->rows(3)->columnSpanFull(),
            ...$buttonFields(),
            Forms\Components\FileUpload::make('image')
                ->label(__('noros-cms::admin.image'))
                ->image()
                ->disk('public')
                ->directory('pages/hero')
                ->visibility('public'),
            Forms\Components\TextInput::make('image_alt')->label(__('noros-cms::admin.image_alternative_text'))->maxLength(255),
        ];

        return [
            Block::make('hero_primary')
                ->label(__('noros-cms::admin.hero_1_image_and_video'))
                ->icon('heroicon-o-photo')
                ->schema([
                    ...$baseHeroFields(),
                    Forms\Components\TextInput::make('video_url')->label(__('noros-cms::admin.video_url'))->url()->maxLength(255),
                ])->columns(2),
            Block::make('hero_secondary')
                ->label(__('noros-cms::admin.hero_2_two_actions'))
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    ...$baseHeroFields(),
                    Forms\Components\TextInput::make('second_button_text')->label(__('noros-cms::admin.second_button_text'))->maxLength(80),
                    Forms\Components\TextInput::make('second_button_url')->label(__('noros-cms::admin.second_button_url'))->maxLength(255),
                ])->columns(2),
            Block::make('hero_slider')
                ->label(__('noros-cms::admin.hero_3_slider'))
                ->icon('heroicon-o-arrows-right-left')
                ->schema([
                    Forms\Components\Repeater::make('slides')
                        ->label(__('noros-cms::admin.slides'))
                        ->schema([
                            Forms\Components\TextInput::make('title')->label(__('noros-cms::admin.title'))->required()->maxLength(180),
                            Forms\Components\Textarea::make('description')->label(__('noros-cms::admin.description'))->rows(3),
                            Forms\Components\TextInput::make('button_text')->label(__('noros-cms::admin.button_text'))->maxLength(80),
                            Forms\Components\TextInput::make('button_url')->label(__('noros-cms::admin.link'))->maxLength(255),
                            Forms\Components\FileUpload::make('image')->label(__('noros-cms::admin.background'))->image()->disk('public')->directory('pages/hero')->visibility('public'),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->collapsible()
                        ->columnSpanFull(),
                ]),
            Block::make('template')
                ->label(__('noros-cms::admin.theme_block'))
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\Select::make('template')
                        ->label(__('noros-cms::admin.block'))
                        ->options(static::templateOptions())
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('spacing')->label(__('noros-cms::admin.bootstrap_spacing_classes'))->placeholder('pt-100 pb-80')->maxLength(120),
                ])->columns(2),
            Block::make('portfolio_widget')
                ->label(__('noros-cms::admin.portfolio_widget'))
                ->icon('heroicon-o-briefcase')
                ->schema([
                    Forms\Components\Select::make('widget_id')
                        ->label(__('noros-cms::admin.project_collection'))
                        ->options(fn (): array => PortfolioWidget::query()->where('is_active', true)->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->preload(),
                ]),
            Block::make('pricing_widget')
                ->label(__('noros-cms::admin.pricing_widget'))
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Forms\Components\Select::make('widget_id')
                        ->label(__('noros-cms::admin.pricing_collection'))
                        ->options(fn (): array => PricingWidget::query()->where('is_active', true)->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->preload(),
                ]),
            Block::make('portfolio_project')
                ->label(__('noros-cms::admin.project_page_template'))
                ->icon('heroicon-o-presentation-chart-bar')
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label(__('noros-cms::admin.main_project_image'))
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('pages/projects')
                        ->visibility('public')
                        ->required(),
                    Forms\Components\TextInput::make('image_alt')->label(__('noros-cms::admin.image_alternative_text'))->maxLength(255),
                    Forms\Components\TextInput::make('title')->label(__('noros-cms::admin.project_name'))->required()->maxLength(180),
                    Forms\Components\Textarea::make('summary')->label(__('noros-cms::admin.short_description'))->rows(3),
                    Forms\Components\Repeater::make('parameters')
                        ->label(__('noros-cms::admin.project_parameters'))
                        ->schema([
                            Forms\Components\TextInput::make('label')->label(__('noros-cms::admin.parameter'))->required(),
                            Forms\Components\TextInput::make('value')->label(__('noros-cms::admin.value'))->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])->columns(2),
            Block::make('content')
                ->label(__('noros-cms::admin.editor_html_content'))
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\RichEditor::make('html')
                        ->label(__('noros-cms::admin.content'))
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages/content')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('classes')->label(__('noros-cms::admin.section_css_classes'))->placeholder('pt-80 pb-80')->maxLength(255),
                ])->columns(2),
            Block::make('html')
                ->label(__('noros-cms::admin.custom_html'))
                ->icon('heroicon-o-code-bracket')
                ->schema([
                    Forms\Components\Textarea::make('html')->label(__('noros-cms::admin.html_inside_the_row'))->rows(12)->required()->columnSpanFull(),
                    Forms\Components\TextInput::make('section_classes')->label(__('noros-cms::admin.section_classes'))->placeholder('pt-80 pb-80')->maxLength(255),
                    Forms\Components\TextInput::make('container_classes')->label(__('noros-cms::admin.container_classes'))->maxLength(255),
                    Forms\Components\TextInput::make('row_classes')->label(__('noros-cms::admin.row_classes'))->maxLength(255),
                    Forms\Components\TextInput::make('content_classes')->label(__('noros-cms::admin.content_classes'))->placeholder('col-12')->maxLength(255),
                    Forms\Components\TextInput::make('margin_top')->label(__('noros-cms::admin.top_margin_px'))->numeric()->minValue(0)->maxValue(1000),
                    Forms\Components\TextInput::make('margin_bottom')->label(__('noros-cms::admin.bottom_margin_px'))->numeric()->minValue(0)->maxValue(1000),
                    Forms\Components\TextInput::make('padding_top')->label(__('noros-cms::admin.top_padding_px'))->numeric()->minValue(0)->maxValue(1000),
                    Forms\Components\TextInput::make('padding_bottom')->label(__('noros-cms::admin.bottom_padding_px'))->numeric()->minValue(0)->maxValue(1000),
                    Forms\Components\TextInput::make('padding_left')->label(__('noros-cms::admin.left_padding_px'))->numeric()->minValue(0)->maxValue(1000),
                    Forms\Components\TextInput::make('padding_right')->label(__('noros-cms::admin.right_padding_px'))->numeric()->minValue(0)->maxValue(1000),
                ])->columns(2),
        ];
    }

    /** @return array<string, string> */
    public static function templateOptions(): array
    {
        return app(BlockRegistry::class)->templateOptions();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('noros-cms::admin.name_0918b4'))->searchable()->sortable()->description(fn (Page $record): string => '/'.$record->path),
                Tables\Columns\TextColumn::make('template')->label(__('noros-cms::admin.template'))->badge()->formatStateUsing(fn (PageTemplate $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')->label(__('noros-cms::admin.status'))->badge()->formatStateUsing(fn (PageStatus $state): string => $state->label()),
                Tables\Columns\IconColumn::make('is_home')->label(__('noros-cms::admin.home'))->boolean(),
                Tables\Columns\TextColumn::make('published_at')->label(__('noros-cms::admin.publication'))->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label(__('noros-cms::admin.updated_cf2e3b'))->since()->sortable(),
            ])
            ->recordActions([
                TranslateContent::make(),
                Action::make('open')
                    ->label(__('noros-cms::admin.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record): string => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn (Page $record): bool => $record->isPublished()),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
