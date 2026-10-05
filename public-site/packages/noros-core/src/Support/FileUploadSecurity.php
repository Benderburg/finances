<?php

namespace Noros\Core\Support;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FileUploadSecurity
{
    public function configure(FileUpload $component): void
    {
        $component->maxSize((int) config('noros-cache.upload_max_kb', 10240));
        // image() is called after configureUsing(), so evaluate the final types
        // when validating instead of letting image/* override the restriction.
        $component->rules(function (FileUpload $component): array {
            $types = $component->getAcceptedFileTypes() ?? [];

            return in_array('image/*', $types, true)
                ? ['mimetypes:image/jpeg,image/png,image/webp,image/gif']
                : [];
        });
        $component->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf', 'text/plain' => 'txt'];
            Validator::make(['file' => $file], [
                'file' => ['file', 'max:'.config('noros-cache.upload_max_kb', 10240), 'mimetypes:'.implode(',', array_keys($extensions))],
            ])->validate();

            return Str::uuid().'.'.$extensions[$file->getMimeType()];
        });
    }
}
