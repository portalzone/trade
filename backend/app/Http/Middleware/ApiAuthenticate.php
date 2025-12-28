<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class ApiAuthenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        // For API routes, don't redirect - throw exception instead
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return null;
    }

    protected function unauthenticated($request, array $guards)
    {
        throw new AuthenticationException(
            'Unauthenticated.', $guards, null
        );
    }
}
