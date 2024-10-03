<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public $rules = [
        'name' => 'required | string | min:3 | max:255',
        'description' => 'nullable | string | min:3 | max:255',
    ];

    public $searchRules = [
        'search' => 'nullable | string',
    ];

    public $messages = [];

    public function __construct()
    {
        $this->middleware('permission:management.roles.*');
        $this->middleware('permission:management.roles.edit')->only(['edit', 'update']);
        $this->middleware('permission:management.roles.create')->only(['create', 'store']);
        $this->middleware('permission:management.roles.destroy')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validated = Validator::make($request->all(), $this->searchRules, $this->messages)->validate();
        $query = Role::where('name', '!=', 'Super-Admin')->orderBy('id', 'desc');

        if (isset($validated['search']) && $search = $validated['search']) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->paginate(20);

        return view('management.roles.index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::all();

        return view('management.roles.create', [
            'role' => null,
            'permissions' => $permissions,
            'syncedPerms' => collect(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), $this->rules, $this->messages)->validate();

        // add additional data in order to store
        $validated['guard_name'] = 'web';
        $role = Role::create($validated);

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        return redirect(route('roles.index'))
            ->with('success', 'Created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $permissions = Permission::all();
        $syncedPerms = $role->permissions()->pluck('id');

        return view('management.roles.create', [
            'role' => $role,
            'permissions' => $permissions,
            'syncedPerms' => $syncedPerms,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validated = Validator::make($request->all(), $this->rules, $this->messages)->validate();

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);
        } else {
            $role->syncPermissions([]);
        }

        if ($role->update($validated)) {
            return redirect()->route('roles.index')
                ->with('success', 'Updated successfully.');
        }

        return redirect()->route('roles.index')
            ->with('error', 'An error occurred, Try again.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->delete()) {
            return redirect(route('roles.index'))
                ->with('success', 'Removed successfully.');
        }

        return redirect(route('roles.index'))
            ->with('error', 'An error occurred, Try again.');
    }
}
