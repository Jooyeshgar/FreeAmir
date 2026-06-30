<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct() {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::query()
            ->unless(auth()->user()->can('users.edit'), function ($query) {
                $query->whereHas('companies', function ($query) {
                    $query->where('companies.id', getActiveCompany());
                });
            })
            ->with('employee')
            ->paginate(30);

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::where('name', '!=', 'Super-Admin')->get();
        $companies = Company::all();

        return view('users.create', compact('roles', 'companies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ]);

        if (! $request->has('role')) {
            throw ValidationException::withMessages([__('The User must have at least one role.')]);
        }

        if (! $request->has('company')) {
            throw ValidationException::withMessages([__('The User must have at least one company.')]);
        }

        DB::transaction(function () use ($request, &$user) {
            $role = array_values($request->role);
            $company = array_values($request->company);
            $user = new User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->password = bcrypt($request->input('password'));
            $user->save();

            $user->syncRoles($role);
            $user->companies()->sync($company);
        });

        return redirect()->route('users.index')->with('success', __('User created successfully!'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $companyId = getActiveCompany();
        if (! $user->companies()->where('companies.id', $companyId)->exists()) {
            return redirect()->route('users.index')
                ->with('error', __('User does not have access to this company.'));
        }

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $companies = Company::all();
        $employees = Employee::whereDoesntHave('user')->get();

        return view('users.edit', compact('user', 'roles', 'companies', 'employees'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|string|min:8',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        if (! $request->has('role')) {
            throw ValidationException::withMessages([__('The User must have at least one role.')]);
        }

        if (! $request->has('company')) {
            throw ValidationException::withMessages([__('The User must have at least one company.')]);
        }

        if ($request->filled('employee_id')) {
            if ($user->employee()->exists() && $user->employee->id !== (int) $request->employee_id) {
                throw ValidationException::withMessages([__('The user is already linked to an employee.')]);
            }

            $employee = Employee::whereKey($request->employee_id)->whereNull('user_id')->first();
            if (! $employee) {
                throw ValidationException::withMessages([__('The selected employee is already assigned to another user.')]);
            }
        }

        DB::transaction(function () use ($request, $user, $employee) {
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $role = array_values($request->role);
            $company = array_values($request->company);

            if ($request->input('password')) {
                $user->password = bcrypt($request->input('password'));
            }

            $user->save();

            if ($request->filled('employee_id')) {
                $employee->update(['user_id' => $user->id]);
            }

            $user->syncRoles($role);
            $user->companies()->sync($company);
        });

        return redirect()->route('users.index')->with('success', __('User updated successfully!'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $companyId = getActiveCompany();
        if (! $user->companies()->where('companies.id', $companyId)->exists()) {
            return redirect()->route('users.index')
                ->with('error', __('User does not have access to this company.'));
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', __('User deleted successfully!'));
    }

    public function createEmployee(Request $request, User $user): RedirectResponse
    {
        $companyId = getActiveCompany();

        if (! $user->companies()->where('companies.id', $companyId)->exists()) {
            return redirect()->route('users.index')
                ->with('error', __('User does not have access to this company.'));
        }

        $existingEmployee = $user->employee()->first();
        if ($existingEmployee) {
            return redirect()->route('hr.employees.show', $existingEmployee);
        }

        $workSite = WorkSite::query()->orderBy('id')->first();
        $workShift = WorkShift::query()->orderBy('id')->first();

        if (! $workSite || ! $workShift) {
            return redirect()->route('users.index')
                ->with('error', __('Please create a work site and work shift first.'));
        }

        [$firstName, $lastName] = $this->splitName($user->name);

        $employee = Employee::create([
            'company_id' => $companyId,
            'code' => $this->uniqueEmployeeCode($user->id),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', __('Employee created successfully.'));
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        $firstName = $parts[0] ?? '';
        $lastName = trim(implode(' ', array_slice($parts ?? [], 1)));

        if ($lastName === '') {
            $lastName = $firstName;
        }

        return [$firstName, $lastName];
    }

    private function uniqueEmployeeCode(int $userId): string
    {
        $base = 'EMP-'.$userId;
        $code = $base;
        $counter = 1;

        while (Employee::withoutGlobalScopes()->where('code', $code)->exists()) {
            $code = Str::limit($base.'-'.$counter, 20, '');
            $counter++;
        }

        return $code;
    }
}
