<?php

namespace Noros\Cms\Models;

class PricingWidget extends TranslatableModel
{
    protected $fillable = ['name', 'eyebrow', 'title', 'description', 'currency', 'plans', 'tasks', 'is_active'];

    protected function casts(): array
    {
        return [
            'plans' => 'array',
            'tasks' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
