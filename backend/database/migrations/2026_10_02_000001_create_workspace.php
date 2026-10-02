<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function base(Blueprint $t): void
    {
        $t->engine = 'InnoDB';
        $t->uuid('id')->primary();
        $t->foreignUuid('user_id')->constrained('users');
        $t->unique(['user_id', 'id']);
        $t->timestamps();
        $t->unsignedBigInteger('revision')->default(1);
    }

    private function link(string $table, string $column, string $parent): void
    {
        Schema::table($table, fn (Blueprint $t) => $t->foreign(['user_id', $column])->references(['user_id', 'id'])->on($parent));
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Financial migrations require MySQL 8.4.');
        }
        Schema::create('user_settings', function (Blueprint $t) {
            $t->foreignUuid('user_id')->primary()->constrained('users');
            $t->enum('locale', ['ro', 'ru', 'en'])->default('ro');
            $t->enum('base_currency_code', ['MDL', 'EUR', 'USD'])->default('MDL');
            $t->enum('theme', ['light', 'dark', 'system'])->default('system');
            $t->string('timezone')->default('Europe/Chisinau');
            $t->unsignedBigInteger('workspace_revision')->default(1);
            $t->unsignedBigInteger('workspace_generation')->default(1);
        });
        Schema::create('accounts', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->enum('kind', ['regular', 'savings']);
            $t->enum('currency_code', ['MDL', 'EUR', 'USD']);
            $t->bigInteger('opening_balance_minor')->default(0);
            $t->boolean('include_in_total')->default(true);
            $t->timestamp('archived_at')->nullable();
        });
        Schema::create('categories', function (Blueprint $t) {
            $this->base($t);
            $t->enum('kind', ['income', 'expense']);
            $t->string('system_code')->nullable();
            $t->string('name')->nullable();
            $t->string('name_key')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamp('archived_at')->nullable();
            $t->json('legacy_metadata')->nullable();
            $t->unique(['user_id', 'kind', 'name_key']);
            $t->unique(['user_id', 'system_code']);
        });
        Schema::create('goals', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('icon', 32)->nullable();
            $t->bigInteger('target_amount_minor');
            $t->enum('currency_code', ['MDL', 'EUR', 'USD']);
            $t->date('deadline')->nullable();
            $t->uuid('savings_account_id')->nullable()->unique();
            $t->timestamp('cancelled_at')->nullable();
            $t->bigInteger('legacy_saved_minor')->nullable();
            $t->string('legacy_status')->nullable();
            $t->timestamp('legacy_completed_at')->nullable();
        });
        Schema::create('operations', function (Blueprint $t) {
            $this->base($t);
            $t->enum('type', ['income', 'expense', 'transfer', 'exchange']);
            $t->enum('status', ['posted', 'voided'])->default('posted');
            $t->date('occurred_on');
            $t->string('description', 2000)->default('');
            foreach (['account_id', 'from_account_id', 'to_account_id', 'category_id', 'goal_id'] as $c) {
                $t->uuid($c)->nullable();
            }
            $t->bigInteger('amount_minor');
            $t->enum('currency_code', ['MDL', 'EUR', 'USD']);
            $t->bigInteger('target_amount_minor')->nullable();
            $t->enum('target_currency_code', ['MDL', 'EUR', 'USD'])->nullable();
            $t->decimal('quoted_rate', 30, 12)->nullable();
            $t->decimal('effective_rate', 30, 12)->nullable();
            $t->boolean('goal_completion_requested')->default(false);
            $t->timestamp('voided_at')->nullable();
            $t->json('legacy_metadata')->nullable();
            $t->index(['user_id', 'occurred_on', 'created_at', 'id']);
            $t->index(['user_id', 'account_id']);
            $t->index(['user_id', 'from_account_id']);
            $t->index(['user_id', 'to_account_id']);
        });
        Schema::create('operation_revisions', function (Blueprint $t) {
            $this->base($t);
            $t->uuid('operation_id');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('action');
            $t->json('before_payload')->nullable();
            $t->json('after_payload');
        });
        Schema::create('budget_templates', function (Blueprint $t) {
            $this->base($t);
            $t->uuid('category_id');
            $t->enum('currency_code', ['MDL', 'EUR', 'USD']);
            $t->bigInteger('limit_minor');
            $t->date('start_month');
            $t->date('stop_month')->nullable();
        });
        Schema::create('budgets', function (Blueprint $t) {
            $this->base($t);
            $t->uuid('category_id');
            $t->enum('currency_code', ['MDL', 'EUR', 'USD']);
            $t->bigInteger('limit_minor');
            $t->date('period_month');
            $t->boolean('disabled')->default(false);
            $t->uuid('source_template_id')->nullable();
            $t->unique(['user_id', 'category_id', 'period_month']);
        });
        Schema::create('liabilities', function (Blueprint $t) {
            $this->base($t);
            $t->enum('kind', ['receivable', 'payable', 'credit']);
            $t->string('counterparty_name');
            $t->bigInteger('principal_minor');
            $t->enum('currency_code', ['MDL', 'EUR', 'USD']);
            $t->date('due_on')->nullable();
            $t->string('comment', 2000)->default('');
            $t->timestamp('cancelled_at')->nullable();
        });
        Schema::create('liability_settlements', function (Blueprint $t) {
            $this->base($t);
            $t->uuid('liability_id')->unique();
            $t->uuid('operation_id')->unique();
        });
        Schema::create('command_deduplication', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->constrained('users');
            $t->uuid('command_key');
            $t->unsignedBigInteger('workspace_generation');
            $t->string('request_hash', 64);
            $t->json('response');
            $t->timestamp('created_at');
            $t->unique(['user_id', 'command_key']);
        });
        foreach (['import_previews', 'restore_backups'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->uuid('id')->primary();
                $t->foreignUuid('user_id')->constrained('users');
                $t->longText('payload');
                $t->timestamp('created_at');
                if ($table === 'import_previews') {
                    $t->string('file_hash', 64);
                    $t->unsignedBigInteger('expected_workspace_revision');
                    $t->timestamp('expires_at');
                    $t->json('manifest');
                }
            });
        }
        Schema::create('migration_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users');
            $t->string('source_hash', 64);
            $t->json('report');
            $t->timestamp('created_at');
            $t->unique(['user_id', 'source_hash']);
        });
        Schema::create('migration_id_map', function (Blueprint $t) {
            $t->id();
            $t->uuid('run_id');
            $t->string('entity');
            $t->uuid('source_id');
            $t->uuid('target_id');
        });
        Schema::create('admin_audit', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->foreignUuid('subject_id')->constrained('users');
            $t->json('before_payload');
            $t->json('after_payload');
            $t->timestamp('created_at');
        });
        $this->link('goals', 'savings_account_id', 'accounts');
        foreach (['account_id', 'from_account_id', 'to_account_id'] as $c) {
            $this->link('operations', $c, 'accounts');
        }
        $this->link('operations', 'category_id', 'categories');
        $this->link('operations', 'goal_id', 'goals');
        $this->link('operation_revisions', 'operation_id', 'operations');
        $this->link('budgets', 'category_id', 'categories');
        $this->link('budget_templates', 'category_id', 'categories');
        $this->link('budgets', 'source_template_id', 'budget_templates');
        $this->link('liability_settlements', 'liability_id', 'liabilities');
        $this->link('liability_settlements', 'operation_id', 'operations');
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_opening CHECK (opening_balance_minor BETWEEN 0 AND 999999999999999)');
        foreach (['operations' => ['amount_minor', 'target_amount_minor'], 'goals' => ['target_amount_minor'], 'budgets' => ['limit_minor'], 'budget_templates' => ['limit_minor'], 'liabilities' => ['principal_minor']] as $table => $columns) {
            foreach ($columns as $c) {
                DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_{$c}_range CHECK ($c BETWEEN 1 AND 999999999999999)");
            }
        }
        DB::statement("ALTER TABLE operations ADD CONSTRAINT operations_shape CHECK ((type IN ('income','expense') AND account_id IS NOT NULL AND category_id IS NOT NULL AND from_account_id IS NULL AND to_account_id IS NULL AND target_amount_minor IS NULL AND target_currency_code IS NULL AND quoted_rate IS NULL AND effective_rate IS NULL) OR (type IN ('transfer','exchange') AND account_id IS NULL AND category_id IS NULL AND goal_id IS NULL AND goal_completion_requested=0 AND from_account_id IS NOT NULL AND to_account_id IS NOT NULL AND from_account_id <> to_account_id AND target_amount_minor IS NOT NULL AND target_currency_code IS NOT NULL AND effective_rate > 0 AND ((type='transfer' AND currency_code=target_currency_code AND amount_minor=target_amount_minor AND effective_rate=1) OR (type='exchange' AND currency_code<>target_currency_code))))");
        DB::statement("ALTER TABLE operations ADD CONSTRAINT operations_goal CHECK ((goal_id IS NULL AND goal_completion_requested=0) OR (goal_id IS NOT NULL AND type='expense'))");
    }

    public function down(): void
    {
        foreach (['admin_audit', 'migration_id_map', 'migration_runs', 'import_previews', 'restore_backups', 'command_deduplication', 'liability_settlements', 'liabilities', 'budgets', 'budget_templates', 'operation_revisions', 'operations', 'goals', 'categories', 'accounts', 'user_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
