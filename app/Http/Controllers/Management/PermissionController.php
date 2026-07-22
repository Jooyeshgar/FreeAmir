<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public $searchRules = [
        'search' => 'nullable | string',
    ];

    public $messages = [];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = Validator::make($request->all(), $this->searchRules, $this->messages)->validate();

        $query = Permission::query()->withCount('roles')->orderBy('name');
        if (isset($validated['search']) and $search = $validated['search']) {
            $query->where('name', 'like', "%{$search}%");
        }
        $permissions = $query->paginate(20)->withQueryString();

        $view = $request->session()->get('interface_mode') === 'management'
            && $request->user()->can('access-super-admin-panel')
                ? 'management.permission.index'
                : 'management.permission.workspace-index';

        return view($view, [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::query()->orderBy('name')->get();

        return view('management.permission.create', [
            'permission' => null,
            'roles' => $roles,
            'syncedRoles' => collect(old('roles')),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'name' => 'required|string|min:1|max:255|unique:permissions,name',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ], $this->messages)->validate();

        $perm = Permission::create([
            'name' => $validatedData['name'],
            'guard_name' => 'web',
        ]);

        $roles = Role::whereIn('id', $validatedData['roles'])->get();
        $perm->syncRoles($roles);

        return redirect(route('permissions.index'))
            ->with('success', __('Permission create successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Permission $permission)
    {
        return view('management.permission.create', [
            'permission' => $permission,
            'roles' => Role::query()->orderBy('name')->get(),
            'syncedRoles' => $permission->roles()->pluck('id'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        $validated = Validator::make($request->all(), [
            'name' => "required|string|min:1|max:255|unique:permissions,name,{$permission->id}",
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ], $this->messages)->validate();

        $roles = Role::whereIn('id', $validated['roles'])->get();
        $permission->syncRoles($roles);

        if ($permission->update(['name' => $validated['name']])) {
            return redirect(route('permissions.index'))
                ->with('success', __('Permission updated successfully'));
        }

        return redirect(route('permissions.index'))
            ->with('error', __('An error occurred, Try again.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        if ($permission->delete()) {
            return redirect(route('permissions.index'))
                ->with('success', __('Permission deleted successfully.'));
        }

        return redirect(route('permissions.index'))
            ->with('error', __('An error occurred, Try again.'));
    }
}
