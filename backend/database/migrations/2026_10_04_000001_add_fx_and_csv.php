<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['accounts', 'goals', 'operations', 'budget_templates', 'budgets', 'liabilities'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY currency_code ENUM('MDL','EUR','USD','RON') NOT NULL");
        }
        DB::statement("ALTER TABLE operations MODIFY target_currency_code ENUM('MDL','EUR','USD','RON') NULL");
        DB::statement("ALTER TABLE user_settings MODIFY base_currency_code ENUM('MDL','EUR','USD','RON') NOT NULL DEFAULT 'MDL'");
        Schema::create('fx_reference_rates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('provider', 20);
            $t->string('currency_code', 3);
            $t->date('effective_on');
            $t->decimal('mdl_per_unit', 36, 18);
            $t->string('published_nominal', 32);
            $t->string('published_value', 64);
            $t->json('provenance');
            $t->timestamp('fetched_at');
            $t->unsignedInteger('version');
            $t->unique(['provider', 'currency_code', 'effective_on', 'version'], 'fx_version_unique');
            $t->index(['currency_code', 'effective_on']);
        });
        Schema::create('csv_previews', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('generation');
            $t->unsignedBigInteger('workspace_revision');
            $t->string('source_hash', 64);
            $t->longText('payload');
            $t->timestamp('expires_at');
        });
        Schema::create('csv_import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('source_key', 64);
            $t->unsignedBigInteger('generation');
            // Receipt survives JSON restore: never cascade from an operation.
            $t->uuid('operation_id');
            $t->timestamp('created_at');
            $t->unique(['user_id', 'source_key']);
        });
    }

    public function down(): void
    {
        // Shrinking currency enums would destroy RON data. Roll forward instead.
        throw new RuntimeException('Stage B has data-preserving forward migrations only.');
    }
};
