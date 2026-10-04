<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StageBController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Requests\CommandRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Shared hosting can cache static files for a year. With the public copies
// removed, these URLs pass through Laravel and retain revalidation headers.
foreach (['sw.js' => 'application/javascript', 'manifest.webmanifest' => 'application/manifest+json'] as $asset => $type) {
    Route::get($asset, function () use ($asset, $type) {
        $file = is_file(public_path($asset)) ? public_path($asset) : resource_path('pwa/'.$asset);
        abort_unless(is_file($file), 503);

        return response()->file($file, ['Content-Type' => $type, 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/']);
    })->withoutMiddleware('web');
}

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgot']);
    Route::post('reset-password', [AuthController::class, 'reset']);
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verify'])->name('verification.verify');
    Route::get('confirm-email/{id}/{hash}', [AuthController::class, 'confirmEmail'])->middleware('signed')->name('email.confirm');
    Route::middleware(['auth:sanctum', 'auth.session'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('resend-verification', [AuthController::class, 'resend'])->middleware('throttle:verification');
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('email-change', [AuthController::class, 'emailChange']);
    });
});
Route::prefix('api/v1')->middleware(['auth:sanctum', 'auth.session'])->group(function () {
    Route::get('me', [WorkspaceController::class, 'me']);
    Route::patch('me', [WorkspaceController::class, 'profile']);
    Route::middleware('verified')->group(function () {
        Route::get('dashboard', [WorkspaceController::class, 'dashboard']);
        Route::get('fx/reference', [StageBController::class, 'reference'])->middleware('throttle:reports');
        Route::post('operations/quote', [StageBController::class, 'quote'])->middleware('throttle:reports');
        Route::get('operations/export.csv', [StageBController::class, 'export'])->middleware('throttle:reports');
        Route::post('csv/preview', [StageBController::class, 'preview'])->middleware('throttle:imports');
        Route::post('csv/apply', [StageBController::class, 'apply'])->middleware('throttle:imports');
        Route::post('budgets/ensure-month', fn (CommandRequest $r) => app(WorkspaceController::class)->mutate($r, 'budgets', null, 'ensure-month'));
        Route::patch('goals/{id}/expenses/{operation}', [WorkspaceController::class, 'amendGoalExpense']);
        foreach (['accounts' => ['archive', 'unarchive'], 'categories' => ['archive', 'unarchive'], 'operations' => ['void'], 'goals' => ['spend', 'cancel', 'resume'], 'liabilities' => ['settle', 'void-settlement', 'cancel', 'resume'], 'budget-templates' => ['stop']] as $resource => $actions) {
            foreach ($actions as $action) {
                Route::post($resource.'/{id}/'.$action, fn (CommandRequest $r, string $id) => app(WorkspaceController::class)->mutate($r, $resource, $id, $action));
            }
        }
        foreach (['accounts', 'categories', 'operations', 'goals', 'liabilities', 'budgets', 'budget-templates'] as $resource) {
            Route::get($resource, fn (Request $r) => app(WorkspaceController::class)->index($r, $resource));
            Route::post($resource, fn (CommandRequest $r) => app(WorkspaceController::class)->mutate($r, $resource));
            Route::get($resource.'/{id}', fn (Request $r, string $id) => app(WorkspaceController::class)->show($r, $resource, $id));
            Route::patch($resource.'/{id}', fn (CommandRequest $r, string $id) => app(WorkspaceController::class)->mutate($r, $resource, $id));
            if (in_array($resource, ['accounts', 'categories', 'goals', 'budgets'])) {
                Route::delete($resource.'/{id}', fn (CommandRequest $r, string $id) => app(WorkspaceController::class)->mutate($r, $resource, $id));
            }
        }
        foreach (['cash-flow', 'expenses', 'balances'] as $kind) {
            Route::get('reports/'.$kind, [WorkspaceController::class, 'report'])->defaults('kind', $kind)->middleware('throttle:reports');
        }
        Route::get('backup/export', [WorkspaceController::class, 'export'])->middleware('throttle:reports');
        Route::post('backup/preview', [WorkspaceController::class, 'preview'])->middleware('throttle:imports');
        Route::post('backup/apply', [WorkspaceController::class, 'apply'])->middleware('throttle:imports');
        Route::get('backup/previous/{id}', [WorkspaceController::class, 'previousBackup']);
        Route::get('admin/stats', [AdminController::class, 'stats']);
        Route::get('admin/users', [AdminController::class, 'users']);
        Route::patch('admin/users/{id}', [AdminController::class, 'update']);
    });
});
Route::any('api/{path}', fn () => abort(404))->where('path', '.*');
Route::any('auth/{path}', fn () => abort(404))->where('path', '.*');
Route::get('/{path?}', function () {
    $file = public_path('build/index.html');
    if (! is_file($file)) {
        return response('Build frontend first: cd frontend && npm ci && npm run build', 503);
    }

    return response()->file($file, ['Cache-Control' => 'no-cache']);
})->where('path', '^(?!sanctum(?:/|$)).*');
