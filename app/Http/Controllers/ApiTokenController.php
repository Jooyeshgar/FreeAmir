<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $permissions = $request->user()->getAllPermissions()
            ->whereNotIn('name', ['api.access', 'api-tokens.index', 'api-tokens.store', 'api-tokens.destroy'])
            ->sortBy('name')
            ->values();

        $tokens = $request->user()->tokens()->latest()->get();

        return view('api-tokens.index', compact('permissions', 'tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $availablePermissions = $request->user()->getAllPermissions()->pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'in:'.implode(',', $availablePermissions)],
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
