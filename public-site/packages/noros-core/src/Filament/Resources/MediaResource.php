<?php

namespace Noros\Core\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Noros\Core\Models\Media;
use Noros\Core\Support\MediaService;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        return Gate::inspect('manage_media');
    }

    public static function getModelLabel(): string
    {
        return __('noros-core::admin.ui_media_file');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-core::admin.ui_media');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.ui_platform');
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [TextInput::make('title')->label(__('noros-core::admin.ui_title'))->maxLength(255)];
        foreach (config('noros.locales') as $locale => $label) {
            $fields[] = TextInput::make('translations.'.$locale)->label(__('noros-core::admin.ui_alt_text').$label)->maxLength(1000);
        }

        return $schema->columns(1)->components($fields);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label(__('noros-core::admin.ui_title'))->searchable(),
            TextColumn::make('path')->label(__('noros-core::admin.ui_path'))->searchable()->url(fn (Media $record): string => $record->url)->openUrlInNewTab(),
            TextColumn::make('mime_type')->label(__('noros-core::admin.ui_mime_type')),
            TextColumn::make('size')->numeric()->label(__('noros-core::admin.ui_bytes')),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->using(function (Media $record): bool {
                app(MediaService::class)->delete($record);

                return true;
            }),
        ])->headerActions([
            Action::make('upload')->label(__('noros-core::admin.ui_upload'))->authorize('manage_media')->schema([
                TextInput::make('title')->label(__('noros-core::admin.ui_title'))->maxLength(255),
                FileUpload::make('file')->label(__('noros-core::admin.ui_file'))->required()->storeFiles(false)->maxSize(10240)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/plain']),
            ])->action(function (array $data): void {
                app(MediaService::class)->upload($data['file'], $data['title'] ?? null);
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => MediaResource\Pages\ManageMedia::route('/')];
    }
}
