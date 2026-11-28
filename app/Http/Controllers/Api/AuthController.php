<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenAbilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, ApiTokenAbilityService $apiTokenAbilityService): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('The provided credentials do not match our records.'),
            ]);
        }

        if (config('app.email_verification') && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => __('Please verify your account before continuing.'),
            ]);
        }

        if (! $user->can('api.access')) {
            throw ValidationException::withMessages([
                'email' => __('You do not have permission to access the API.'),
            ]);
        }

        $availablePermissions = $apiTokenAbilityService->userAbilities($user)->all();

        if (empty($availablePermissions)) {
            throw ValidationException::withMessages([
                'email' => __('No API permissions are available for this user.'),
            ]);
        }

        $requestedPermissions = $credentials['permissions'] ?? $availablePermissions;

        $tokenAbilities = array_values(array_intersect($requestedPermissions, $availablePermissions));

        if (empty($tokenAbilities)) {
            throw ValidationException::withMessages([
                'permissions' => __('None of the requested permissions are allowed for this user.'),
            ]);
        }

        $deviceName = $credentials['device_name'] ?? 'api-login';

        $token = $user->createToken($deviceName, $tokenAbilities);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $tokenAbilities,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('Logged out successfully.'),
        ]);
    }
}
