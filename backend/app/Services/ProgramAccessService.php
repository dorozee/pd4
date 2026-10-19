<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Profile;
use App\Models\Subscription;
use App\Models\User;

/**
 * Управление доступом пользователя к дням программы.
 *
 * Пробный период: 10 дней с момента заполнения профиля.
 * Подписка (169 ₽): все дни программы до окончания срока.
 */
class ProgramAccessService
{
    public const TRIAL_DAYS = 10;

    public const PRICE_KOPECKS = 16900;

    public function __construct(private readonly WeightProjectionService $projection)
    {
    }

    /**
     * Возвращает подписку с «ленивой» проверкой истечения срока.
     */
    public function effectiveSubscription(User $user): ?Subscription
    {
        $subscription = $user->subscription()->first();

        if (! $subscription) {
            return null;
        }

        if ($subscription->status !== SubscriptionStatus::Expired) {
            $expired = match ($subscription->status) {
                SubscriptionStatus::Trial => $subscription->trial_ends_at !== null
                    && $subscription->trial_ends_at->isPast(),
                SubscriptionStatus::Active => $subscription->expires_at !== null
                    && $subscription->expires_at->isPast(),
                default => false,
            };

            if ($expired) {
                $subscription->update(['status' => SubscriptionStatus::Expired]);
            }
        }

        return $subscription;
    }

    /**
     * Список номеров дней, открытых пользователю.
     *
     * @return array<int, int>
     */
    public function accessibleDays(?Subscription $subscription, int $durationDays = 30): array
    {
        if (! $subscription) {
            return [];
        }

        return match ($subscription->status) {
            SubscriptionStatus::Trial => range(1, min(self::TRIAL_DAYS, $durationDays)),
            SubscriptionStatus::Active => range(1, $durationDays),
            default => [],
        };
    }

    public function canAccessDay(User $user, int $day): bool
    {
        $subscription = $this->effectiveSubscription($user);

        $program = $subscription?->program;

        return in_array($day, $this->accessibleDays($subscription, $program?->duration_days ?? 30), true);
    }

    /**
     * Создаёт пробную подписку на активную программу.
     */
    public function startTrial(User $user, ?Program $program = null): Subscription
    {
        $program ??= $this->activeProgram();

        return Subscription::create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => SubscriptionStatus::Trial,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
            'price_kopecks' => $program->price_kopecks ?? self::PRICE_KOPECKS,
        ]);
    }

    public function activeProgram(): \App\Models\Program
    {
        $program = \App\Models\Program::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        abort_if(! $program, 500, 'Активная программа не настроена. Обратитесь к администратору.');

        return $program;
    }

    /**
     * Полная сводка по программе для пользователя (для API).
     */
    public function programOverview(User $user): ?array
    {
        $subscription = $this->effectiveSubscription($user);
        $profile = $user->profile;

        if (! $subscription || ! $profile) {
            return null;
        }

        $program = $subscription->program()->with('days')->first();
        $accessible = $this->accessibleDays($subscription, $program->duration_days);
        $currentDay = $this->currentDay($subscription);

        return [
            'id' => $program->id,
            'title' => $program->title,
            'description' => $program->description,
            'duration_days' => $program->duration_days,
            'price_kopecks' => $program->price_kopecks,
            'subscription' => [
                'status' => $subscription->status->value,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
                'price_kopecks' => $subscription->price_kopecks,
                'is_paid' => $subscription->isPaid(),
            ],
            'current_day' => $currentDay,
            'accessible_days' => $accessible,
            'days' => $program->days->map(fn ($day) => [
                'day_number' => $day->day_number,
                'title' => $day->title,
                'phase' => $day->phase->value,
                'accessible' => in_array($day->day_number, $accessible, true),
            ])->values()->all(),
            'projection' => $this->projection->for($profile),
        ];
    }

    /**
     * Текущий день программы считается от даты старта (заполнения профиля).
     */
    public function currentDay(Subscription $subscription): int
    {
        $start = $subscription->starts_at ?? $subscription->created_at;

        if (! $start) {
            return 1;
        }

        $day = (int) $start->startOfDay()->diffInDays(now()) + 1;

        return max(1, min($day, $subscription->program?->duration_days ?? 30));
    }
}
