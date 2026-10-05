<?php

namespace Noros\Core\Policies;

use Noros\Core\Models\SystemTranslation;
use Noros\Core\Models\User;

class SystemTranslationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_translations');
    }

    public function view(User $user, SystemTranslation $translation): bool
    {
        return $user->can('manage_translations');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_translations');
    }

    public function update(User $user, SystemTranslation $translation): bool
    {
        return $user->can('manage_translations');
    }

    public function delete(User $user, SystemTranslation $translation): bool
    {
        return $user->can('manage_translations') && $translation->is_custom;
    }
}
