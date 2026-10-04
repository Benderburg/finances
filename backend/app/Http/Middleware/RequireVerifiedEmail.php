<?php

namespace App\Http\Middleware;

use App\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;

final class RequireVerifiedEmail
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            throw new DomainError('EMAIL_NOT_VERIFIED', 403);
        }

        return $next($request);
    }
}
