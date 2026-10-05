<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $fields */
class ContentTranslation extends Model
{
    protected $table = 'cms_content_translations';

    protected $fillable = ['entity_type', 'entity_id', 'locale', 'slug', 'path', 'fields'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }
}
