<?php

use App\Domain\CommandBus;
use App\Domain\DomainError;
use App\Domain\FinancialEngine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script,$user,$account,$category,$start] = $argv;
while (microtime(true) < (float) $start) {
    usleep(1000);
}
try {
    app(CommandBus::class)->execute($user, (string) Str::uuid(), 'concurrent-expense', ['amount_minor' => '80000'], fn () => app(FinancialEngine::class)->post($user, ['type' => 'expense', 'account_id' => $account, 'category_id' => $category, 'amount_minor' => '80000', 'occurred_on' => '2026-10-02']));
    echo 'COMMITTED';
} catch (DomainError $e) {
    echo $e->errorCode;
}
