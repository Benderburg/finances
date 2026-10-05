<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table): void {
            $table->json('tags')->nullable();
            $table->json('technologies')->nullable();
            $table->string('client')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('project_url')->nullable();
            $table->json('gallery')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->text('task')->nullable();
            $table->text('solution')->nullable();
            $table->text('result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_projects', fn (Blueprint $table) => $table->dropColumn(['tags', 'technologies', 'client', 'year', 'project_url', 'gallery', 'is_featured', 'task', 'solution', 'result']));
    }
};
