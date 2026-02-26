<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeDutyStatus;
use App\Enums\EmployeeEducationLevel;
use App\Enums\EmployeeEmploymentType;
use App\Enums\EmployeeGender;
use App\Enums\EmployeeInsuranceType;
use App\Enums\EmployeeMaritalStatus;
use App\Enums\EmployeeNationality;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\OrgChart;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Employee::with(['workSite', 'orgChart'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('national_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        $employees = $query->paginate(15)->withQueryString();

        return view('employees.index', compact('employees'));
    }

    public function create(): View
    {
        $workSites = WorkSite::orderBy('name')->get(['id', 'name']);
        $orgCharts = OrgChart::orderBy('title')->get(['id', 'title']);
        $workSiteContracts = WorkSiteContract::orderBy('name')->get(['id', 'name']);
        $workShifts = WorkShift::orderBy('name')->get(['id', 'name']);

        return view('employees.create', array_merge(
            compact('workSites', 'orgCharts', 'workSiteContracts', 'workShifts'),
            self::enumOptions()
        ));
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create(array_merge(
            $request->validated(),
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('employees.index')
            ->with('success', __('Employee created successfully.'));
    }

    public function show(Employee $employee): View
    {
        $employee->load(['workSite', 'orgChart', 'workSiteContract', 'workShift']);

        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee): View
    {
        $workSites = WorkSite::orderBy('name')->get(['id', 'name']);
        $orgCharts = OrgChart::orderBy('title')->get(['id', 'title']);
        $workSiteContracts = WorkSiteContract::orderBy('name')->get(['id', 'name']);
        $workShifts = WorkShift::orderBy('name')->get(['id', 'name']);

        return view('employees.edit', array_merge(
            compact('employee', 'workSites', 'orgCharts', 'workSiteContracts', 'workShifts'),
            self::enumOptions()
        ));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index')
            ->with('success', __('Employee updated successfully.'));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', __('Employee deleted successfully.'));
    }

    /** Build the shared enum options arrays used by views. */
    public static function enumOptions(): array
    {
        return [
            'nationalities' => EmployeeNationality::options(),
            'genders' => array_merge(['' => __('— Select —')], EmployeeGender::options()),
            'maritalStatuses' => array_merge(['' => __('— Select —')], EmployeeMaritalStatus::options()),
            'dutyStatuses' => array_merge(['' => __('— Select —')], EmployeeDutyStatus::options()),
            'insuranceTypes' => array_merge(['' => __('— Select —')], EmployeeInsuranceType::options()),
            'educationLevels' => array_merge(['' => __('— Select —')], EmployeeEducationLevel::options()),
            'employmentTypes' => array_merge(['' => __('— Select —')], EmployeeEmploymentType::options()),
        ];
    }
}
