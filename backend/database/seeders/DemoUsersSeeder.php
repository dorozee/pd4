<?php

namespace Database\Seeders;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $pending = User::firstOrCreate(
            ['email' => 'pending@demo.com'],
            [
                'name' => 'Пётр Ожидает',
                'password' => 'demo12345',
                'status' => UserStatus::Pending,
                'admin' => false,
            ],
        );

        $rejected = User::firstOrCreate(
            ['email' => 'rejected@demo.com'],
            [
                'name' => 'Ольга Отклонена',
                'password' => 'demo12345',
                'status' => UserStatus::Rejected,
                'admin' => false,
            ],
        );

        $approved = User::firstOrCreate(
            ['email' => 'approved@demo.com'],
            [
                'name' => 'Анна Одобрена',
                'password' => 'demo12345',
                'status' => UserStatus::Approved,
                'admin' => false,
            ],
        );

        if (! $approved->profile()->exists()) {
            Profile::create([
                'user_id' => $approved->id,
                'height' => 168,
                'age' => 32,
                'weight' => 82.5,
                'gender' => Gender::Female,
                'activity_level' => ActivityLevel::Light,
            ]);
        }

        if (! $approved->subscription()->exists()) {
            $program = \App\Models\Program::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($program) {
                $approved->subscription()->create([
                    'program_id' => $program->id,
                    'status' => SubscriptionStatus::Trial,
                    'starts_at' => now()->subDays(2),
                    'trial_ends_at' => now()->addDays(8),
                    'price_kopecks' => $program->price_kopecks,
                ]);
            }
        }

        unset($pending, $rejected);
    }
}
