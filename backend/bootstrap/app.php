<?php

use App\Domain\DomainError;
use App\Http\Middleware\PrivateResponses;
use App\Http\Middleware\RequireVerifiedEmail;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->convertEmptyStringsToNull(except: [fn ($r) => $r->is('api/*')]);
        $middleware->alias(['auth.session' => AuthenticateSession::class, 'verified' => RequireVerifiedEmail::class]);
        $middleware->append(PrivateResponses::class);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (QueryException $e) {
            Log::error('Database command failed', ['sqlstate' => $e->errorInfo[0] ?? 'unknown']);

            return false; // Do not write SQL bindings, imported documents or credentials to logs.
        });
        $exceptions->shouldRenderJsonWhen(fn ($r) => $r->is('api/*', 'auth/*', 'sanctum/*') || $r->expectsJson());
        $exceptions->render(function (DomainError $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'fields' => $e->fields, 'details' => $e->details]], $e->httpStatus);
        });
        $exceptions->render(function (ValidationException $e) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Check the form fields', 'fields' => $e->errors(), 'details' => (object) []]], 422);
        });
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Sign in again', 'fields' => (object) [], 'details' => (object) []]], 401);
        });
        $exceptions->render(function (HttpExceptionInterface $e, $r) {
            if ($r->is('api/*', 'auth/*')) {
                return response()->json(['error' => ['code' => match ($e->getStatusCode()) {
                    403 => 'FORBIDDEN',404 => 'NOT_FOUND',419 => 'CSRF_EXPIRED',429 => 'RATE_LIMITED',default => 'REQUEST_FAILED'
                }, 'message' => 'Request could not be completed', 'fields' => (object) [], 'details' => (object) []]], $e->getStatusCode());
            }
        });
        $exceptions->render(function (Throwable $e, $r) {
            if ($r->is('api/*', 'auth/*')) {
                return response()->json(['error' => ['code' => 'SERVER_ERROR', 'message' => 'Request could not be completed', 'fields' => (object) [], 'details' => (object) []]], 500);
            }
        });
    })->create();
