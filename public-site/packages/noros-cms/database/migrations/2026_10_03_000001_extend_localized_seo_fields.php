<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->json('structured_data')->nullable();
        });
        Schema::table('portfolio_projects', function (Blueprint $table): void {
            $table->string('twitter_card', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('structured_data'));
        Schema::table('portfolio_projects', fn (Blueprint $table) => $table->dropColumn('twitter_card'));
    }
};
