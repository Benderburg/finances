<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->string('heading')->nullable());
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('code', 190)->nullable();
            $table->unique(['menu_id', 'code']);
        });
        Schema::create('portfolio_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('heading')->nullable();
            $table->string('category')->nullable();
            $table->text('summary')->nullable();
            $table->json('content')->nullable();
            $table->string('image')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->json('related_slugs')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('cms_content_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('locale', 10);
            $table->string('slug', 190)->nullable();
            $table->string('path', 190)->nullable();
            $table->json('fields');
            $table->timestamps();
            $table->unique(['entity_type', 'entity_id', 'locale'], 'cms_translation_entity_locale');
            $table->unique(['entity_type', 'locale', 'path'], 'cms_translation_locale_path');
        });
        Schema::create('cms_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('locale', 10);
            $table->string('path', 190);
            $table->unique(['entity_type', 'locale', 'path'], 'cms_redirect_locale_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_slug_redirects');
        Schema::dropIfExists('cms_content_translations');
        Schema::dropIfExists('portfolio_projects');
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('heading'));
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropUnique(['menu_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
