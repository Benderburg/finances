<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Noros\Cms\Filament\Actions\TranslateContent;
use Noros\Cms\Filament\CmsResource;
use Noros\Cms\Filament\Forms\StructuredDataField;
use Noros\Cms\Models\PortfolioProject;

class PortfolioProjectResource extends CmsResource
{
    protected static ?string $model = PortfolioProject::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.ui_project');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.ui_projects');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::admin.ui_website');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->label(__('noros-cms::admin.ui_title'))->required()->maxLength(255), TextInput::make('heading')->label(__('noros-cms::admin.ui_heading'))->maxLength(255),
            TextInput::make('slug')->label(__('noros-cms::admin.ui_slug'))->required()->alphaDash()->unique(ignoreRecord: true)->maxLength(190),
            TextInput::make('category')->label(__('noros-cms::admin.ui_category'))->maxLength(255), TagsInput::make('tags')->label(__('noros-cms::admin.ui_tags')), TagsInput::make('technologies')->label(__('noros-cms::admin.ui_technologies')),
            TextInput::make('client')->label(__('noros-cms::admin.ui_client'))->maxLength(255), TextInput::make('year')->label(__('noros-cms::admin.ui_year'))->integer()->minValue(1900)->maxValue(2200),
            TextInput::make('project_url')->label(__('noros-cms::admin.ui_project_url'))->url()->maxLength(255),
            FileUpload::make('image')->label(__('noros-cms::admin.ui_image'))->image()->disk('public')->directory('portfolio'),
            FileUpload::make('gallery')->label(__('noros-cms::admin.ui_gallery'))->image()->multiple()->disk('public')->directory('portfolio'),
            Textarea::make('summary')->label(__('noros-cms::admin.ui_summary'))->maxLength(10000),
            TagsInput::make('content')->label(__('noros-cms::admin.ui_paragraphs'))->columnSpanFull(),
            Textarea::make('task')->label(__('noros-cms::admin.ui_task')), Textarea::make('solution')->label(__('noros-cms::admin.ui_solution')), Textarea::make('result')->label(__('noros-cms::admin.ui_result')),
            TextInput::make('seo_title')->label(__('noros-cms::admin.ui_seo_title'))->maxLength(255), Textarea::make('seo_description')->label(__('noros-cms::admin.ui_seo_description')), Textarea::make('seo_keywords')->label(__('noros-cms::admin.ui_seo_keywords')),
            TextInput::make('canonical_url')->label(__('noros-cms::admin.ui_canonical_url'))->rules(['nullable', 'url:http,https'])->maxLength(255),
            Select::make('seo_robots')->label(__('noros-cms::admin.ui_robots'))->options(['index,follow' => 'index, follow', 'noindex,follow' => 'noindex, follow', 'noindex,nofollow' => 'noindex, nofollow'])->default('index,follow')->required(),
            TextInput::make('og_title')->label(__('noros-cms::admin.open_graph_title'))->maxLength(255),
            Textarea::make('og_description')->label(__('noros-cms::admin.open_graph_description')),
            FileUpload::make('og_image')->label(__('noros-cms::admin.open_graph_image'))->image()->disk('public')->directory('portfolio/social'),
            TextInput::make('og_type')->label(__('noros-cms::admin.ui_open_graph_type'))->maxLength(40)->default('article')->required(),
            Select::make('twitter_card')->label(__('noros-cms::admin.ui_twitter_card'))->options(['summary' => 'summary', 'summary_large_image' => 'summary_large_image']),
            StructuredDataField::make(),
            Toggle::make('is_featured')->label(__('noros-cms::admin.ui_featured')), Select::make('status')->label(__('noros-cms::admin.ui_status'))->options(['draft' => __('noros-cms::admin.ui_draft'), 'published' => __('noros-cms::admin.ui_published')])->required()->default('draft'),
            DateTimePicker::make('published_at')->label(__('noros-cms::admin.ui_publication_date')), Select::make('related_slugs')->label(__('noros-cms::admin.ui_related_projects'))->multiple()->options(fn (): array => PortfolioProject::query()->pluck('title', 'slug')->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label(__('noros-cms::admin.ui_title'))->searchable(), TextColumn::make('category')->label(__('noros-cms::admin.ui_category'))->searchable(), TextColumn::make('status')->label(__('noros-cms::admin.ui_status'))->badge(), TextColumn::make('published_at')->label(__('noros-cms::admin.ui_publication_date'))->dateTime()])
            ->headerActions([CreateAction::make()])->recordActions([TranslateContent::make(), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => PortfolioProjectResource\Pages\ManageProjects::route('/')];
    }
}
