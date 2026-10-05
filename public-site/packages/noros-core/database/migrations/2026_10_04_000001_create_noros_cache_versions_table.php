<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noros_cache_versions', function (Blueprint $table): void {
            $table->string('group', 100)->primary();
            $table->uuid('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noros_cache_versions');
    }
};
