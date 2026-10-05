<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->boolean('allow_ratings')->default(true);
        });
        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('post_id')->nullable()->change();
            $table->nullableMorphs('commentable');
        });
        Schema::create('cms_ratings', function (Blueprint $table): void {
            $table->id();
            $table->morphs('rateable');
            $table->string('visitor_hash', 64);
            $table->unsignedTinyInteger('score');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['rateable_type', 'rateable_id', 'visitor_hash'], 'cms_ratings_unique_visitor');
        });
    }

    public function down(): void
    {
        if (DB::table('comments')->whereNull('post_id')->exists()) {
            throw new RuntimeException('Generic comments exist. Export or migrate them before rolling back engagement.');
        }
        Schema::dropIfExists('cms_ratings');
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropMorphs('commentable');
        });
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('allow_ratings');
        });
        // post_id stays nullable: generic comments must not be deleted by a schema rollback.
    }
};
