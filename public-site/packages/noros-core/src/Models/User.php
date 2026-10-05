<?php

namespace Noros\Core\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Noros\Core\Database\Factories\UserFactory;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $actor = auth()->user();
            if ($actor instanceof User && ! $actor->can('manage_roles') && $user->isDirty('email') && filled(config('noros.bootstrap_admin_email')) && $user->email === config('noros.bootstrap_admin_email')) {
                throw ValidationException::withMessages(['email' => 'This address is reserved for the bootstrap administrator.']);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'position',
        'bio',
        'website',
        'social_links',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'social_links' => 'array',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        $bootstrapEmail = config('noros.bootstrap_admin_email');
        if (filled($bootstrapEmail) && $this->getRawOriginal('email') === $bootstrapEmail) {
            return true;
        }

        return $this->roles()->get()->contains(fn (Role $role): bool => in_array($permission, $role->permissions, true));
    }

    public function getAvatarUrlAttribute(): string
    {
        if (blank($this->avatar)) {
            return asset(config('noros.default_avatar', 'favicon.ico'));
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        if (str_starts_with($this->avatar, 'img/')) {
            return asset($this->avatar);
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermission('access_admin');
    }
}
