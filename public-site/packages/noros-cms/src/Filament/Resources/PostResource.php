<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Noros\Cms\Enums\PostStatus;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Forms\StructuredDataField;
use Noros\Cms\Filament\Resources\PostResource\Pages;
use Noros\Cms\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.blog');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.article_1347fb');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.articles');
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make(__('noros-cms::admin.main_content'))
                ->schema([
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
                        ->label(__('noros-cms::admin.url_identifier'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText(__('noros-cms::admin.used_in_the_address_blog_url_identifier')),
                    Forms\Components\TextInput::make('subtitle')
                        ->label(__('noros-cms::admin.subtitle'))
                        ->maxLength(255),
                    Forms\Components\Textarea::make('excerpt')
                        ->label(__('noros-cms::admin.short_description'))
                        ->rows(3)
                        ->maxLength(600)
                        ->helperText(__('noros-cms::admin.shown_on_the_article_card_uses_a_text_excerpt_when_empty'))
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('content')
                        ->label(__('noros-cms::admin.article_content'))
                        ->required()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('blog/content')
                        ->fileAttachmentsVisibility('public')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpan(['lg' => 2]),

            Section::make(__('noros-cms::admin.publication'))
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label(__('noros-cms::admin.status'))
                        ->options(PostStatus::options())
                        ->default(PostStatus::Draft->value)
                        ->required(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label(__('noros-cms::admin.publication_date'))
                        ->seconds(false)
                        ->required(fn (Get $get): bool => in_array(
                            $get('status'),
                            [PostStatus::Published->value, PostStatus::Scheduled->value],
                            true,
                        ))
                        ->helperText(__('noros-cms::admin.a_date_is_required_for_published_and_scheduled_content')),
                    Forms\Components\Select::make('author_id')
                        ->label(__('noros-cms::admin.author'))
                        ->relationship('author', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn (): ?int => auth()->id())
                        ->required(),
                    Forms\Components\Select::make('category_id')
                        ->label(__('noros-cms::admin.category'))
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('tags')
                        ->label(__('noros-cms::admin.tags'))
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('reading_time')
                        ->label(__('noros-cms::admin.reading_time_min'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(999)
                        ->helperText(__('noros-cms::admin.leave_empty_to_calculate_automatically')),
                    Forms\Components\Toggle::make('is_featured')
                        ->label(__('noros-cms::admin.featured_article')),
                    Forms\Components\Toggle::make('allow_comments')
                        ->label(__('noros-cms::admin.allow_comments'))
                        ->default(true),
                    Forms\Components\Toggle::make('allow_ratings')
                        ->label(__('noros-cms::admin.allow_ratings'))
                        ->default(true),
                    Forms\Components\Select::make('engagement_overrides.comments_enabled')
                        ->label(__('noros-cms::engagement.comments_enabled'))
                        ->options([1 => __('noros-cms::engagement.on'), 0 => __('noros-cms::engagement.off')])->placeholder(__('noros-cms::engagement.inherit'))->nullable(),
                    Forms\Components\Select::make('engagement_overrides.ratings_enabled')
                        ->label(__('noros-cms::engagement.ratings_enabled'))
                        ->options([1 => __('noros-cms::engagement.on'), 0 => __('noros-cms::engagement.off')])->placeholder(__('noros-cms::engagement.inherit'))->nullable(),
                ])
                ->columns(2)
                ->columnSpan(['lg' => 1]),

            Section::make(__('noros-cms::admin.cover_and_project'))
                ->schema([
                    Forms\Components\FileUpload::make('cover_image')
                        ->label(__('noros-cms::admin.cover'))
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('blog/covers')
                        ->visibility('public'),
                    Forms\Components\TextInput::make('cover_alt')
                        ->label(__('noros-cms::admin.cover_alternative_text'))
                        ->maxLength(255)
                        ->helperText(__('noros-cms::admin.briefly_describe_the_image_for_accessibility_and_search')),
                    Forms\Components\TextInput::make('project_url')
                        ->label(__('noros-cms::admin.project_url'))
                        ->url()
                        ->maxLength(255)
                        ->prefixIcon('heroicon-o-arrow-top-right-on-square'),
                    Forms\Components\FileUpload::make('og_image')
                        ->label(__('noros-cms::admin.social_media_image'))
                        ->image()
                        ->disk('public')
                        ->directory('blog/social')
                        ->visibility('public')
                        ->helperText(__('noros-cms::admin.uses_the_cover_image_when_empty')),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make(__('noros-cms::admin.ui_seo'))
                ->description(__('noros-cms::admin.fields_for_search_results_and_social_media_previews'))
                ->schema([
                    Forms\Components\TextInput::make('seo_title')
                        ->label(__('noros-cms::admin.seo_title'))
                        ->maxLength(70),
                    Forms\Components\TextInput::make('canonical_url')
                        ->label(__('noros-cms::admin.ui_canonical_url'))
                        ->rules(['nullable', 'url:http,https'])
                        ->maxLength(255),
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
                    Forms\Components\Select::make('twitter_card')
                        ->label(__('noros-cms::admin.ui_twitter_card'))
                        ->options([
                            'summary_large_image' => __('noros-cms::admin.large_image'),
                            'summary' => __('noros-cms::admin.compact_card'),
                        ])
                        ->default('summary_large_image'),
                    Forms\Components\Textarea::make('seo_description')
                        ->label(__('noros-cms::admin.ui_meta_description'))
                        ->rows(3)
                        ->maxLength(170),
                    Forms\Components\Textarea::make('seo_keywords')
                        ->label(__('noros-cms::admin.keywords'))
                        ->rows(3)
                        ->helperText(__('noros-cms::admin.separate_items_with_commas')),
                    Forms\Components\TextInput::make('og_title')
                        ->label(__('noros-cms::admin.open_graph_title'))
                        ->maxLength(95),
                    Forms\Components\TextInput::make('og_type')
                        ->label(__('noros-cms::admin.ui_open_graph_type'))
                        ->default('article')
                        ->maxLength(40),
                    Forms\Components\Textarea::make('og_description')
                        ->label(__('noros-cms::admin.open_graph_description'))
                        ->rows(3)
                        ->maxLength(220)
                        ->columnSpanFull(),
                    StructuredDataField::make(),
                ])
                ->columns(2)
                ->collapsed()
                ->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_url')
                    ->label('')
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('noros-cms::admin.title'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Post $record): ?string => $record->category?->name)
                    ->wrap(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label(__('noros-cms::admin.author'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('noros-cms::admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PostStatus $state): string => $state->label())
                    ->color(fn (PostStatus $state): string => match ($state) {
                        PostStatus::Published => 'success',
                        PostStatus::Scheduled => 'info',
                        PostStatus::Archived => 'gray',
                        PostStatus::Draft => 'warning',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->label(__('noros-cms::admin.publication'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('noros-cms::admin.featured'))
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->label(__('noros-cms::admin.views'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ratings_count')
                    ->label(__('noros-cms::admin.ratings'))
                    ->counts('ratings')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('approved_comments_count')
                    ->label(__('noros-cms::admin.comments'))
                    ->counts('approvedComments')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('noros-cms::admin.status'))
                    ->options(PostStatus::options()),
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('noros-cms::admin.category'))
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('author')
                    ->label(__('noros-cms::admin.author'))
                    ->relationship('author', 'name'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label(__('noros-cms::admin.featured')),
            ])
            ->recordActions([
                TranslateContent::make(),
                Action::make('open')
                    ->label(__('noros-cms::admin.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $record): string => route('blog.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Post $record): bool => $record->isPublished()),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
