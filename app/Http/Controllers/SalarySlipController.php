<?php

namespace App\Http\Controllers;

use App\Models\SalarySlip;
use App\Models\BenefitsDeduction;
use App\Models\PayrollPattern;
use App\Models\SalaryPattern;
use Illuminate\Http\Request;

class SalarySlipController extends Controller
{
    public function index()
    {
        // Fetch salary slips with pagination
        $salarySlips = SalarySlip::with('benefitsDeductions', 'payrollPattern')->paginate(10);
        return view('salary_slips.index', compact('salarySlips'));
    }

    public function create()
    {
        // Fetch all benefits and deductions, and salary patterns for selection
        $benefitsDeductions = BenefitsDeduction::all();
        $payrollPatterns = PayrollPattern::all();
        return view('salary_slips.create', compact('benefitsDeductions', 'payrollPatterns'));
    }

    public function store(Request $request)
    {
        // Validate input data
        $request->validate([
            'name' => 'required|string|max:255',
            'daily_wage' => 'required|numeric',
            'hourly_overtime' => 'nullable|numeric',
            'holiday_work' => 'nullable|numeric',
            'friday_work' => 'nullable|numeric',
            'child_allowance' => 'nullable|numeric',
            'housing_allowance' => 'nullable|numeric',
            'food_allowance' => 'nullable|numeric',
            'marriage_allowance' => 'nullable|numeric',
            'payroll_pattern_id' => 'required|exists:payroll_patterns,id',
            'benefits_deductions' => 'nullable|array',
            'benefits_deductions.*' => 'exists:benefits_deductions,id',
            'description' => 'nullable|string',
        ]);

        // Create salary slip
        $salarySlip = SalarySlip::create($request->only([
            'name',
            'daily_wage',
            'hourly_overtime',
            'holiday_work',
            'friday_work',
            'child_allowance',
            'housing_allowance',
            'food_allowance',
            'marriage_allowance',
            'payroll_pattern_id',
            'description',
        ]));

        // Attach selected benefits and deductions
        if ($request->has('benefits_deductions')) {
            $salarySlip->benefitsDeductions()->sync($request->input('benefits_deductions'));
        }

        return redirect()->route('payroll.salary_slips.index')->with('success', 'Salary Slip created successfully.');
    }

    public function edit(SalarySlip $salarySlip)
    {
        // Fetch all benefits, deductions, and salary patterns for editing
        $benefitsDeductions = BenefitsDeduction::all();
        $payrollPatterns = PayrollPattern::all();
        return view('salary_slips.edit', compact('salarySlip', 'benefitsDeductions', 'payrollPatterns'));
    }

    public function update(Request $request, SalarySlip $salarySlip)
    {
        // Validate input data
        $request->validate([
            'name' => 'required|string|max:255',
            'daily_wage' => 'required|numeric',
            'hourly_overtime' => 'nullable|numeric',
            'holiday_work' => 'nullable|numeric',
            'friday_work' => 'nullable|numeric',
            'child_allowance' => 'nullable|numeric',
            'housing_allowance' => 'nullable|numeric',
            'food_allowance' => 'nullable|numeric',
            'marriage_allowance' => 'nullable|numeric',
            'payroll_pattern_id' => 'required|exists:payroll_patterns,id',
            'benefits_deductions' => 'nullable|array',
            'benefits_deductions.*' => 'exists:benefits_deductions,id',
            'description' => 'nullable|string',
        ]);

        // Update salary slip
        $salarySlip->update($request->only([
            'name',
            'daily_wage',
            'hourly_overtime',
            'holiday_work',
            'friday_work',
            'child_allowance',
            'housing_allowance',
            'food_allowance',
            'marriage_allowance',
            'payroll_pattern_id',
            'description',
        ]));

        // Sync selected benefits and deductions
        $data = [];

        foreach ($request->get('benefits_deductions_id') as $index => $id) {
            if ($request->get('benefits_deductions_amount')[$index] ?? 0) {
                // Use append/push method to keep both entries
                $data[] = [
                    'benefits_deductions_id' => $id,
                    'amount' => $request->get('benefits_deductions_amount')[$index]
                ];
            }
        }
        $salarySlip->benefitsDeductions()->detach();
        if (count($data)) {
            $salarySlip->benefitsDeductions()->attach($data);
        }

        return redirect()->route('payroll.salary_slips.index')->with('success', 'Salary Slip updated successfully.');
    }

    public function destroy(SalarySlip $salarySlip)
    {
        $salarySlip->delete();
        return redirect()->route('payroll.salary_slips.index')->with('success', 'Salary Slip deleted successfully.');
    }
}
