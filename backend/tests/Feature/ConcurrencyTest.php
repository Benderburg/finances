<?php

namespace Tests\Feature;

use App\Domain\CommandBus;
use App\Domain\FinancialEngine;
use App\Domain\WorkspaceService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_two_real_processes_cannot_overdraw(): void
    {
        $user = User::create(['email' => 'concurrent@example.test', 'full_name' => 'Concurrent', 'password' => 'long-password-123']);
        app(WorkspaceService::class)->initialize($user->id);
        $a = app(CommandBus::class)->execute($user->id, (string) Str::uuid(), 'create-test-account', [], fn () => app(WorkspaceService::class)->resource($user->id, 'accounts', 'create', null, ['name' => 'Concurrent', 'kind' => 'regular', 'currency_code' => 'MDL', 'opening_balance_minor' => '100000']));
        $id = $a['data']['id'];
        $category = DB::table('categories')->where('user_id', $user->id)->where('system_code', 'food')->value('id');
        $config = config('database.connections.mysql');
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $start = (string) (microtime(true) + 2);
        $one = new Process([PHP_BINARY, base_path('tests/concurrency-worker.php'), $user->id, $id, $category, $start], base_path(), $env);
        $two = clone $one;
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $results = [$one->getOutput(), $two->getOutput()];
        sort($results);
        $this->assertSame(['COMMITTED', 'INSUFFICIENT_FUNDS'], $results, $one->getErrorOutput().$two->getErrorOutput());
        $this->assertSame('20000', app(FinancialEngine::class)->balance($user->id,$id));
    }
}
