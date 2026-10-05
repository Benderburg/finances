<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_write_locks', function (Blueprint $table): void {
            $table->string('name')->primary();
        });
        Schema::create('system_translations', function (Blueprint $table): void {
            $table->id();
            $key = $table->string('key', 190)->unique();
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $key->collation('utf8mb4_bin');
            }
            $table->string('namespace', 100)->index();
            $table->json('translations');
            $table->boolean('is_custom')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_translations');
        Schema::dropIfExists('translation_write_locks');
    }
};
