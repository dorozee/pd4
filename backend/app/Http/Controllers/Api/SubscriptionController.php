<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ProgramAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly ProgramAccessService $access)
    {
    }

    /**
     * Мок-оплата подписки за 169 ₽: открывает все дни программы.
     */
    public function pay(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $this->access->effectiveSubscription($user);

        if (! $subscription || ! $user->profile) {
            return response()->json([
                'message' => 'Сначала заполните профиль: рост, возраст и вес.',
                'code' => 'PROFILE_REQUIRED',
            ], 422);
        }

        if ($subscription->status === SubscriptionStatus::Active) {
            return response()->json($this->payload($user->fresh()), 200);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'amount_kopecks' => $subscription->price_kopecks,
            'method' => 'card',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $durationDays = $subscription->program->duration_days;

        // Подписка открывает всю 30-дневную программу: срок — от старта программы.
        $base = $subscription->starts_at ?? now();
        $expiresAt = $base->copy()->addDays($durationDays);

        if ($expiresAt->isPast()) {
            $expiresAt = now()->addDays($durationDays);
        }

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'expires_at' => $expiresAt,
        ]);

        return response()->json($this->payload($user->fresh(), $payment), 201);
    }

    private function payload($user, ?Payment $payment = null): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
                'admin' => (bool) $user->admin,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'profile' => $user->profile ? [
                'id' => $user->profile->id,
                'height' => $user->profile->height,
                'age' => $user->profile->age,
                'weight' => (float) $user->profile->weight,
                'gender' => $user->profile->gender->value,
                'activity_level' => $user->profile->activity_level->value,
                'created_at' => $user->profile->created_at?->toIso8601String(),
            ] : null,
            'subscription' => [
                'status' => $this->access->effectiveSubscription($user)?->status->value,
                'trial_ends_at' => $user->subscription->trial_ends_at?->toIso8601String(),
                'expires_at' => $user->subscription->expires_at?->toIso8601String(),
                'price_kopecks' => $user->subscription->price_kopecks,
                'is_paid' => $this->access->effectiveSubscription($user)?->isPaid() ?? false,
            ],
            'program' => $this->access->programOverview($user),
            'payment' => $payment ? [
                'id' => $payment->id,
                'amount_kopecks' => $payment->amount_kopecks,
                'status' => $payment->status->value,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ] : null,
        ];
    }
}
