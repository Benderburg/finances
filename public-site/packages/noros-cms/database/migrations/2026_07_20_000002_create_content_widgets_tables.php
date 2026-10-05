<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('projects')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('pricing_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('currency', 10)->default('€');
            $table->json('plans')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_widgets');
        Schema::dropIfExists('portfolio_widgets');
    }
};
