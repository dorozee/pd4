<?php

namespace App\Enums;

enum ProgramPhase: string
{
    case Adaptation = 'adaptation';
    case FatBurn = 'fat_burn';
    case Strengthening = 'strengthening';

    public function label(): string
    {
        return match ($this) {
            self::Adaptation => 'Адаптация',
            self::FatBurn => 'Сжигание жира',
            self::Strengthening => 'Закрепление',
        };
    }
}
