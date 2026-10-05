<?php

namespace Noros\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** @property array<int, string> $permissions */
class Role extends Model
{
    protected $fillable = ['name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if (! is_array($role->getAttribute('permissions')) || array_diff($role->permissions, config('noros.permissions', []))) {
                throw ValidationException::withMessages(['permissions' => 'Unknown permission.']);
            }
        });
    }
}
