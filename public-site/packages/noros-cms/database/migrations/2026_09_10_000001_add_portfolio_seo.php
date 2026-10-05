<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table): void {
            $table->string('canonical_url')->nullable();
            $table->string('seo_robots')->default('index,follow');
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('article');
            $table->json('structured_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_projects', fn (Blueprint $table) => $table->dropColumn(['canonical_url', 'seo_robots', 'og_title', 'og_description', 'og_image', 'og_type', 'structured_data']));
    }
};
