<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Noros\Cms\Models\Concerns\HasContentTranslations;
use Noros\Core\Support\TranslationLibrary;

abstract class TranslatableModel extends Model
{
    use HasContentTranslations;

    protected bool $useTranslatedAttributes = true;

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            app(TranslationLibrary::class)->lockForWriting();
            $previous = $this->useTranslatedAttributes;
            $this->useTranslatedAttributes = false;
            try {
                return parent::save($options);
            } finally {
                $this->useTranslatedAttributes = $previous;
            }
        });
    }
}
