<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalarySlipRequest;
use App\Http\Requests\UpdateSalarySlipRequest;
use App\Models\SalarySlip;
use App\Models\BenefitsDeduction;
use App\Models\PayrollPattern;

class SalarySlipController extends Controller
{
    public function index()
    {
        $salarySlips = SalarySlip::with('benefitsDeductions', 'payrollPattern')->paginate(10);
        return view('salary_slips.index', compact('salarySlips'));
    }

    public function create()
    {
        $benefitsDeductions = BenefitsDeduction::all();
        $payrollPatterns = PayrollPattern::all();
        return view('salary_slips.create', compact('benefitsDeductions', 'payrollPatterns'));
    }

    public function store(StoreSalarySlipRequest $request)
    {
        $validatedData = $request->validated();

        $salarySlip = SalarySlip::create($validatedData);

        if ($request->has('benefits_deductions')) {
            $salarySlip->benefitsDeductions()->sync($request->input('benefits_deductions'));
        }

        return redirect()->route('payroll.salary_slips.index')->with('success', 'Salary Slip created successfully.');
    }

    public function edit(SalarySlip $salarySlip)
    {
        $benefitsDeductions = BenefitsDeduction::all();
        $payrollPatterns = PayrollPattern::all();
        return view('salary_slips.edit', compact('salarySlip', 'benefitsDeductions', 'payrollPatterns'));
    }

    public function update(UpdateSalarySlipRequest $request, SalarySlip $salarySlip)
    {
        $validatedData = $request->validated();
        $salarySlip->update($validatedData);

        // Prepare benefits and deductions data
        $data = [];
        foreach ($request->get('benefits_deductions_id', []) as $index => $id) {
            if ($request->get('benefits_deductions_amount')[$index] ?? 0) {
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
