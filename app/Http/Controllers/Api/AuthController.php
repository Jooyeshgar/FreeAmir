<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiTokenAbilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * لاگین و دریافت توکن Sanctum
     *
     * Body:
     * - email (required)
     * - password (required)
     * - device_name (optional) - نام دستگاه/توکن
     * - permissions (optional) - آرایه permissionها. اگر ندهی همه permissionهای مجاز کاربر داده می‌شود
     */
    public function login(Request $request, ApiTokenAbilityService $apiTokenAbilityService): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        // نرمال‌سازی ایمیل (مثل LoginController وب)
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials do not match our records.')],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // چک تأیید ایمیل (اگر فعال باشد)
        if (config('app.email_verification') && ! $user->hasVerifiedEmail()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [__('Please verify your account before continuing.')],
            ]);
        }

        // کاربر باید permission دسترسی به API داشته باشد
        if (! $user->can('api.access')) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [__('You do not have permission to access the API.')],
            ]);
        }

        // permissionهای مجاز برای توکن
        $availablePermissions = $apiTokenAbilityService->userAbilities($user)->all();

        if (empty($availablePermissions)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [__('No API permissions are available for this user.')],
            ]);
        }

        $requestedPermissions = $credentials['permissions'] ?? $availablePermissions;

        // فقط permissionهایی که کاربر واقعاً دارد
        $tokenAbilities = array_values(array_intersect($requestedPermissions, $availablePermissions));

        if (empty($tokenAbilities)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'permissions' => [__('None of the requested permissions are allowed for this user.')],
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

    /**
     * خروج و باطل کردن توکن فعلی
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('Logged out successfully.'),
        ]);
    }
}