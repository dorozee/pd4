<?php

namespace App\Services;

use App\Enums\Gender;
use App\Models\Profile;

/**
 * Персональный расчёт программы по параметрам пользователя.
 *
 * Формула базового обмена — Миффлина—Сан Жеора. Дефицит ограничен
 * безопасным темпом: не более 1% массы тела в неделю, калораж не
 * опускается ниже 1200 ккал (женщины) / 1500 ккал (мужчины).
 */
class WeightProjectionService
{
    /** Энергетическая стоимость килограмма жировой ткани. */
    private const KCAL_PER_KG = 7700;

    private const MIN_WEEKLY_LOSS_PERCENT = 0.01;

    public function for(Profile $profile): array
    {
        $weight = (float) $profile->weight;
        $height = (float) $profile->height;
        $age = (int) $profile->age;
        $isMale = $profile->gender === Gender::Male;

        // Базовый обмен (Mifflin—St Jeor)
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + ($isMale ? 5 : -161);

        // Суточный расход с учётом активности
        $tdee = $bmr * $profile->activity_level->multiplier();

        // Планируемый дефицит: базовые 400 ккал, но не быстрее 1% массы тела в неделю
        $maxSafeDeficitPerDay = ($weight * self::MIN_WEEKLY_LOSS_PERCENT * self::KCAL_PER_KG) / 7;
        $deficit = min(400, $maxSafeDeficitPerDay);

        // Не уходим ниже безопасного минимума калорий
        $floor = $isMale ? 1500 : 1200;
        if ($tdee - $deficit < $floor) {
            $deficit = max(0, $tdee - $floor);
        }

        $targetCalories = max($floor, $tdee - $deficit);

        $lossPerDay = $deficit / self::KCAL_PER_KG;
        $loss10 = $lossPerDay * 10;
        $loss30 = $lossPerDay * 30;

        $heightM = $height / 100;
        $bmiNow = $weight / ($heightM * $heightM);
        $weightAfter30 = max(0, $weight - $loss30);
        $bmiAfter = $weightAfter30 / ($heightM * $heightM);

        return [
            'start_weight' => round($weight, 1),
            'current_weight' => round($weight, 1),
            'bmi_now' => round($bmiNow, 1),
            'bmi_after' => round($bmiAfter, 1),
            'daily_calories' => (int) round($targetCalories),
            'daily_deficit' => (int) round($deficit),
            'weekly_loss_kg' => round($lossPerDay * 7, 2),
            'loss_kg_10_days' => round($loss10, 1),
            'loss_kg_30_days' => round($loss30, 1),
            'weight_after_10_days' => round($weight - $loss10, 1),
            'weight_after_30_days' => round($weightAfter30, 1),
            'safe' => true,
            'disclaimer' => 'Расчёт по формуле Миффлина—Сан Жеора с безопасным дефицитом: '
                .'не более 1% массы тела в неделю, калораж не ниже '.($isMale ? '1500' : '1200')
                .' ккал. Рекомендации не заменяют консультацию врача.',
        ];
    }
}
