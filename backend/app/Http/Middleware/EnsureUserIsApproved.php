<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пользователь должен быть одобрен администратором (status = approved),
 * чтобы пользоваться приложением.
 */
class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Не авторизован.'], 401);
        }

        if ($user->status === UserStatus::Pending) {
            return response()->json([
                'message' => 'Аккаунт ожидает одобрения администратором.',
                'code' => 'PENDING_APPROVAL',
            ], 403);
        }

        if ($user->status === UserStatus::Rejected) {
            return response()->json([
                'message' => 'Доступ отклонён администратором.',
                'code' => 'REJECTED',
            ], 403);
        }

        return $next($request);
    }
}
