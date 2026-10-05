<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->json('engagement_overrides')->nullable();
        });
        Schema::table('comments', function (Blueprint $table): void {
            $table->string('submission_hash', 64)->nullable()->unique();
            $table->index(['commentable_type', 'commentable_id', 'status', 'parent_id', 'created_at'], 'comments_public_listing');
        });
        Schema::table('cms_ratings', function (Blueprint $table): void {
            $table->foreignId('comment_id')->nullable()->unique()->constrained('comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cms_ratings', function (Blueprint $table): void {
            $table->dropForeign(['comment_id']);
            $table->dropUnique(['comment_id']);
            $table->dropColumn('comment_id');
        });
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_public_listing');
            $table->dropUnique(['submission_hash']);
            $table->dropColumn('submission_hash');
        });
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('engagement_overrides'));
    }
};
