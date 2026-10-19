<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к Horizon только для пользователей с admin = true.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->admin) {
            abort(403, 'Панель Horizon доступна только администратору.');
        }

        return $next($request);
    }
}
