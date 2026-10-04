<?php

// Run from the private Timeweb backend directory; prints hashes/counts only.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables = ['users', 'user_settings', 'accounts', 'categories', 'goals', 'operations', 'operation_revisions', 'budgets', 'budget_templates', 'liabilities', 'liability_settlements'];
$hash = hash_init('sha256');
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = 0;
    hash_update($hash, $table);
    foreach (Illuminate\Support\Facades\DB::table($table)->orderBy($table === 'user_settings' ? 'user_id' : 'id')->cursor() as $row) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
        $counts[$table]++;
    }
}
echo json_encode(['data_sha256' => hash_final($hash), 'env_sha256' => hash_file('sha256', '.env'), 'counts' => $counts], JSON_THROW_ON_ERROR).PHP_EOL;
