<?php

namespace Noros\Cms\Models;

class PortfolioWidget extends TranslatableModel
{
    protected $fillable = ['name', 'eyebrow', 'title', 'description', 'projects', 'is_active'];

    protected function casts(): array
    {
        return [
            'projects' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
