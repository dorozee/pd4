<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgramAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function __construct(private readonly ProgramAccessService $access)
    {
    }

    /**
     * Обзор программы: прогресс, доступные дни, прогноз веса.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $overview = $this->access->programOverview($user);

        if (! $overview) {
            return response()->json([
                'message' => 'Сначала заполните профиль: рост, возраст и вес.',
                'code' => 'PROFILE_REQUIRED',
            ], 422);
        }

        return response()->json($overview);
    }

    /**
     * Содержимое конкретного дня программы.
     */
    public function day(Request $request, int $day): JsonResponse
    {
        $user = $request->user();

        $subscription = $this->access->effectiveSubscription($user);

        if (! $subscription || ! $user->profile) {
            return response()->json([
                'message' => 'Сначала заполните профиль: рост, возраст и вес.',
                'code' => 'PROFILE_REQUIRED',
            ], 422);
        }

        $program = $subscription->program;

        if ($day < 1 || $day > $program->duration_days) {
            return response()->json(['message' => 'Такого дня в программе нет.'], 404);
        }

        $programDay = $program->days()->where('day_number', $day)->first();

        if (! $programDay) {
            return response()->json(['message' => 'День программы не найден.'], 404);
        }

        $accessible = $this->access->accessibleDays($subscription, $program->duration_days);

        if (! in_array($day, $accessible, true)) {
            $message = $subscription->status === \App\Enums\SubscriptionStatus::Trial
                ? 'Бесплатный период закончился. Оформите подписку за 169 ₽, чтобы продолжить программу.'
                : 'День доступен по подписке. Оформите подписку за 169 ₽.';

            return response()->json([
                'message' => $message,
                'code' => 'SUBSCRIPTION_REQUIRED',
                'price_kopecks' => $subscription->price_kopecks,
            ], 402);
        }

        return response()->json([
            'day_number' => $programDay->day_number,
            'title' => $programDay->title,
            'phase' => $programDay->phase->value,
            'nutrition' => $programDay->nutrition,
            'workout' => $programDay->workout,
            'tips' => $programDay->tips,
            'accessible' => true,
        ]);
    }
}
