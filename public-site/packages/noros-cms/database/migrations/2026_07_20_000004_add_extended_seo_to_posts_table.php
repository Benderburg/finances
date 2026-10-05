<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('seo_robots')->default('index,follow')->after('canonical_url');
            $table->string('og_title')->nullable()->after('seo_robots');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_type', 40)->default('article')->after('og_image');
            $table->string('twitter_card', 40)->default('summary_large_image')->after('og_type');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['seo_robots', 'og_title', 'og_description', 'og_type', 'twitter_card']);
        });
    }
};
