<?php

namespace Noros\Core\Filament\Resources\SystemTranslationResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
use Noros\Core\Filament\Resources\SystemTranslationResource;
use Noros\Core\Models\SystemTranslation;
use Noros\Core\Support\TranslationLibrary;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageTranslations extends ManageRecords
{
    protected static string $resource = SystemTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label(__('noros-core::admin.ui_import_translations'))->schema([
                Textarea::make('json')->label(__('noros-core::admin.ui_json_document'))->required()->maxLength(2097152)->rows(12),
                Toggle::make('dry_run')->label(__('noros-core::admin.ui_preview_only'))->default(true),
            ])->action(function (array $data, TranslationLibrary $library): void {
                Gate::authorize('manage_translations');
                $preview = (bool) $data['dry_run'];
                $result = $library->import($data['json'], $preview);
                Notification::make()->success()->title($preview ? __('noros-core::admin.ui_import_preview') : __('noros-core::admin.ui_translations_imported'))
                    ->body(__('noros-core::admin.ui_import_summary_created_new_updated_updated', ['created' => $result['created'], 'updated' => $result['updated']]))->send();
            }),
            Action::make('export')->label(__('noros-core::admin.ui_export_translations'))->schema([
                Select::make('locale')->label(__('noros-core::admin.ui_language'))->options(config('noros.locales'))->placeholder(__('noros-core::admin.ui_all_languages')),
                Select::make('namespaces')->label(__('noros-core::admin.ui_namespaces'))->multiple()->options(fn (): array => SystemTranslation::query()
                    ->distinct()->orderBy('namespace')->pluck('namespace', 'namespace')->all()),
            ])->action(function (array $data, TranslationLibrary $library): StreamedResponse {
                Gate::authorize('manage_translations');
                $json = $library->export($data['locale'] ?? null, $data['namespaces'] ?? []);

                return response()->streamDownload(function () use ($json): void {
                    echo $json;
                }, 'translations.json', ['Content-Type' => 'application/json; charset=UTF-8']);
            }),
        ];
    }
}
