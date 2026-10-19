<?php

namespace App\Enums;

enum ActivityLevel: string
{
    case Sedentary = 'sedentary';
    case Light = 'light';
    case Moderate = 'moderate';
    case Active = 'active';
    case VeryActive = 'very_active';

    public function label(): string
    {
        return match ($this) {
            self::Sedentary => 'Сидячий',
            self::Light => 'Лёгкий',
            self::Moderate => 'Средний',
            self::Active => 'Высокий',
            self::VeryActive => 'Очень высокий',
        };
    }

    /**
     * Коэффициент активности для расчёта суточного расхода (TDEE).
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::Sedentary => 1.2,
            self::Light => 1.375,
            self::Moderate => 1.55,
            self::Active => 1.725,
            self::VeryActive => 1.9,
        };
    }
}
