<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public $rules = [
        'name' => 'required | string | min:1 | max:255',
        'description' => 'nullable | string | min:3 | max:255',
    ];

    public $searchRules = [
        'search' => 'nullable | string',
    ];

    public $messages = [];

    public function __construct()
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validated = Validator::make($request->all(), $this->searchRules, $this->messages)->validate();

        $query = Permission::orderBy('id', 'desc');
        if (isset($validated['search']) and $search = $validated['search']) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }
        $permissions = $query->paginate(20);

        return view('management.permission.index', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::all();

        return view('management.permission.create', [
            'permission' => null,
            'roles' => $roles,
            'syncedRoles' => collect(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), $this->rules, $this->messages)->validate();

        // add additional data in order to store
        $validatedData['guard_name'] = 'web';
        $perm = Permission::create($validatedData);

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            $perm->syncRoles($roles);
        }

        return redirect(route('permissions.index'))
            ->with('success', __('Permission create successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Permission $permission)
    {
        $roles = Role::all();
        $syncedRoles = $permission->roles()->pluck('id');

        return view('management.permission.create', [
            'permission' => $permission,
            'roles' => $roles,
            'syncedRoles' => $syncedRoles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Permission $permission)
    {
        $validated = Validator::make($request->all(), $this->rules, $this->messages)->validate();

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            $permission->syncRoles($roles);
        } else {
            $permission->syncRoles([]);
        }

        if ($permission->update($validated)) {
            return redirect(route('permissions.index'))
                ->with('success', __('Permission updated successfully'));
        }

        return redirect(route('permissions.index'))
            ->with('error', __('An error occurred, Try again.'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
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
