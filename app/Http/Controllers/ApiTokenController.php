<?php

namespace App\Http\Controllers;

use App\Services\ApiTokenAbilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $tokens = $request->user()->tokens()->latest()->get();

        return view('api-tokens.index', compact('tokens'));
    }

    public function create(Request $request, ApiTokenAbilityService $apiTokenAbilityService): View
    {
        $permissions = $apiTokenAbilityService->userAbilities($request->user());

        return view('api-tokens.create', compact('permissions'));
    }

    public function store(Request $request, ApiTokenAbilityService $apiTokenAbilityService): RedirectResponse
    {
        $availablePermissions = $apiTokenAbilityService->userAbilities($request->user())->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in($availablePermissions)],
        ]);

        $token = $request->user()->createToken($validated['name'], $validated['permissions']);

        return redirect()->route('api-tokens.index')
            ->with('success', __('API token created successfully.'))
            ->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->firstOrFail();
        $token->delete();

        return redirect()->route('api-tokens.index')
            ->with('success', __('API token deleted successfully.'));
    }
}
