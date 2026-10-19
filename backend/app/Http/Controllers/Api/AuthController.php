<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProgramAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ProgramAccessService $access)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'] ?? strstr($data['email'], '@', true) ?: 'Пользователь',
            'email' => $data['email'],
            // Пароль хранится только в виде хэша (cast 'hashed' в модели)
            'password' => $data['password'],
            'status' => UserStatus::Pending,
            'admin' => false,
        ]);

        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'subscription.program']);

        return response()->json([
            'user' => $this->userPayload($user),
            'profile' => $user->profile ? [
                'id' => $user->profile->id,
                'height' => $user->profile->height,
                'age' => $user->profile->age,
                'weight' => (float) $user->profile->weight,
                'gender' => $user->profile->gender->value,
                'activity_level' => $user->profile->activity_level->value,
                'created_at' => $user->profile->created_at?->toIso8601String(),
            ] : null,
            'subscription' => $user->subscription ? $this->subscriptionPayload($user) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Вы вышли из системы.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'admin' => (bool) $user->admin,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function subscriptionPayload(User $user): array
    {
        $subscription = $this->access->effectiveSubscription($user);

        if (! $subscription) {
            return [
                'status' => null,
                'trial_ends_at' => null,
                'expires_at' => null,
                'price_kopecks' => ProgramAccessService::PRICE_KOPECKS,
                'is_paid' => false,
            ];
        }

        return [
            'status' => $subscription->status->value,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'price_kopecks' => $subscription->price_kopecks,
            'is_paid' => $subscription->isPaid(),
        ];
    }
}
