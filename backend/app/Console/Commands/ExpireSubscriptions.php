<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Помечает истёкшие подписки: пробный период дольше 10 дней
 * или истёкший срок оплаченной подписки.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Помечает истёкшие пробные периоды и подписки как expired';

    public function handle(): int
    {
        $now = now();

        $trials = Subscription::query()
            ->where('status', SubscriptionStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->update(['status' => SubscriptionStatus::Expired->value]);

        $paid = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->update(['status' => SubscriptionStatus::Expired->value]);

        $this->info("Истекло пробных: {$trials}, подписок: {$paid}.");

        return self::SUCCESS;
    }
}
