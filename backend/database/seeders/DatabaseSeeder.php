<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Production identities are created by registration or the reviewed cutover import.
        // For an explicit local fixture use: artisan norocel:local-user <email>.
    }
}
