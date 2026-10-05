<?php

namespace Noros\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property string|null $title
 * @property array<string, string|null>|null $translations
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property-read string $url
 */
class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['title', 'translations', 'disk', 'path', 'mime_type', 'size'];

    protected function casts(): array
    {
        return ['translations' => 'array', 'size' => 'integer'];
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
