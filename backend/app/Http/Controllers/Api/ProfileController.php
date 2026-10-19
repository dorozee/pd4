<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\ProgramAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProgramAccessService $access)
    {
    }

    /**
     * Заполнение профиля после одобрения: рост, возраст, вес и т. д.
     * Создаёт профиль и запускает 10-дневный пробный период.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile) {
            return response()->json([
                'message' => 'Профиль уже заполнен.',
                'code' => 'PROFILE_EXISTS',
            ], 422);
        }

        $data = $request->validate([
            'height' => ['required', 'integer', 'min:100', 'max:250'],
            'age' => ['required', 'integer', 'min:16', 'max:80'],
            'weight' => ['required', 'numeric', 'min:30', 'max:300'],
            'gender' => ['required', 'in:male,female'],
            'activity_level' => [
                'required',
                'in:sedentary,light,moderate,active,very_active',
            ],
        ]);

        $profile = Profile::create([
            'user_id' => $user->id,
            'height' => $data['height'],
            'age' => $data['age'],
            'weight' => $data['weight'],
            'gender' => $data['gender'],
            'activity_level' => $data['activity_level'],
        ]);

        if (! $user->subscription()->exists()) {
            $this->access->startTrial($user);
        }

        return response()->json($this->mePayload($user->fresh()), 201);
    }

    private function mePayload($user): array
    {
        $user->load(['profile', 'subscription.program']);

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
            'subscription' => $user->subscription ? [
                'status' => $user->subscription->status->value,
                'trial_ends_at' => $user->subscription->trial_ends_at?->toIso8601String(),
                'expires_at' => $user->subscription->expires_at?->toIso8601String(),
                'price_kopecks' => $user->subscription->price_kopecks,
                'is_paid' => $user->subscription->isPaid(),
            ] : null,
            'program' => $this->access->programOverview($user),
        ];
    }
}
