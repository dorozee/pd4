<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Пробный период',
            self::Active => 'Активна',
            self::Expired => 'Истекла',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::Expired => 'danger',
        };
    }
}
