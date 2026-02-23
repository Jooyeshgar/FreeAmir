<?php

namespace App\Http\Controllers;

use App\Models\DecreeBenefit;
use App\Models\Employee;
use App\Models\OrgChart;
use App\Models\PayrollElement;
use App\Models\SalaryDecree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalaryDecreeController extends Controller
{
    public function index(Request $request): View
    {
        $query = SalaryDecree::with(['employee', 'orgChart'])
            ->orderBy('start_date', 'desc');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $decrees = $query->paginate(15);
        $employees = Employee::orderBy('first_name')->get();

        return view('salary-decrees.index', compact('decrees', 'employees'));
    }

    public function create(): View
    {
        $employees = Employee::orderBy('first_name')->get();
        $orgCharts = OrgChart::orderBy('title')->get();
        $payrollElements = PayrollElement::orderBy('title')->get();

        return view('salary-decrees.create', compact('employees', 'orgCharts', 'payrollElements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'org_chart_id' => ['required', 'integer', 'exists:org_charts,id'],
            'name' => ['nullable', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_type' => ['nullable', 'in:full_time,part_time,hourly,shift'],
            'daily_wage' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'benefits' => ['nullable', 'array'],
            'benefits.*.element_id' => ['required', 'integer', 'exists:payroll_elements,id'],
            'benefits.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $decree = SalaryDecree::create([
                'company_id' => getActiveCompany(),
                'employee_id' => $validated['employee_id'],
                'org_chart_id' => $validated['org_chart_id'],
                'name' => $validated['name'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'contract_type' => $validated['contract_type'] ?? null,
                'daily_wage' => $validated['daily_wage'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            foreach ($validated['benefits'] ?? [] as $benefit) {
                DecreeBenefit::create([
                    'decree_id' => $decree->id,
                    'element_id' => $benefit['element_id'],
                    'element_value' => $benefit['value'],
                ]);
            }
        });

        return redirect()->route('salary-decrees.index')
            ->with('success', __('Salary decree created successfully.'));
    }

    public function edit(SalaryDecree $salaryDecree): View
    {
        $salaryDecree->load('benefits.element');

        $employees = Employee::orderBy('first_name')->get();
        $orgCharts = OrgChart::orderBy('title')->get();
        $payrollElements = PayrollElement::orderBy('title')->get();

        return view('salary-decrees.edit', compact('salaryDecree', 'employees', 'orgCharts', 'payrollElements'));
    }

    public function update(Request $request, SalaryDecree $salaryDecree): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'org_chart_id' => ['required', 'integer', 'exists:org_charts,id'],
            'name' => ['nullable', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_type' => ['nullable', 'in:full_time,part_time,hourly,shift'],
            'daily_wage' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'benefits' => ['nullable', 'array'],
            'benefits.*.element_id' => ['required', 'integer', 'exists:payroll_elements,id'],
            'benefits.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request, $salaryDecree) {
            $salaryDecree->update([
                'employee_id' => $validated['employee_id'],
                'org_chart_id' => $validated['org_chart_id'],
                'name' => $validated['name'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'contract_type' => $validated['contract_type'] ?? null,
                'daily_wage' => $validated['daily_wage'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $salaryDecree->benefits()->delete();

            foreach ($validated['benefits'] ?? [] as $benefit) {
                DecreeBenefit::create([
                    'decree_id' => $salaryDecree->id,
                    'element_id' => $benefit['element_id'],
                    'element_value' => $benefit['value'],
                ]);
            }
        });

        return redirect()->route('salary-decrees.index')
            ->with('success', __('Salary decree updated successfully.'));
    }

    public function destroy(SalaryDecree $salaryDecree): RedirectResponse
    {
        $salaryDecree->benefits()->delete();
        $salaryDecree->delete();

        return redirect()->route('salary-decrees.index')
            ->with('success', __('Salary decree deleted successfully.'));
    }
}
