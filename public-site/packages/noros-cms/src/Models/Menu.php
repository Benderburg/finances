<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends TranslatableModel
{
    protected $fillable = ['name', 'location', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id')->where('is_active', true);
    }
}
