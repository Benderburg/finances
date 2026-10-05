<?php

namespace Noros\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Noros\Core\Models\Role;
use Noros\Core\Models\User;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'administrator'], ['permissions' => config('noros.permissions')]);
        Role::firstOrCreate(['name' => 'super-admin'], ['permissions' => config('noros.permissions')]);
        Role::firstOrCreate(['name' => 'content-manager'], ['permissions' => ['access_admin', 'manage_pages', 'manage_blog', 'manage_portfolio', 'manage_menus', 'manage_media', 'manage_translations']]);
        Role::firstOrCreate(['name' => 'moderator'], ['permissions' => ['access_admin', 'manage_comments', 'moderate_comments', 'manage_ratings']]);
        Role::firstOrCreate(['name' => 'editor'], ['permissions' => ['access_admin', 'manage_pages', 'manage_blog', 'manage_comments', 'moderate_comments', 'manage_ratings', 'manage_portfolio', 'manage_menus', 'manage_media', 'manage_translations']]);
        Role::firstOrCreate(['name' => 'shop-manager'], ['permissions' => ['access_admin', 'manage_products', 'manage_orders', 'manage_customers']]);
        $this->call(SystemTranslationsSeeder::class);
        $bootstrapEmail = config('noros.bootstrap_admin_email');
        if (filled($bootstrapEmail)) {
            $model = config('auth.providers.users.model', User::class);
            $existingAdmin = $model::query()->where('email', $bootstrapEmail)->first();
            if ($existingAdmin) {
                $existingAdmin->roles()->syncWithoutDetaching([Role::where('name', 'administrator')->firstOrFail()->getKey()]);
            }
        }
    }
}
