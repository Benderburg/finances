<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['post_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_ratings');
    }
};
