<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBenefitsDeductionRequest;
use App\Http\Requests\UpdateBenefitsDeductionRequest;
use App\Models\BenefitsDeduction;
use Illuminate\Http\Request;

class BenefitsDeductionController extends Controller
{
    public function index()
    {
        $benefitsDeductions = BenefitsDeduction::paginate(10);
        return view('benefits_deductions.index', compact('benefitsDeductions'));
    }

    public function create()
    {
        return view('benefits_deductions.create');
    }

    public function store(StoreBenefitsDeductionRequest $request)
    {
        $validatedData = $request->validated();

        // Convert checkbox values to boolean
        $data = $validatedData;
        $data['insurance_included'] = $request->has('insurance_included');
        $data['tax_included'] = $request->has('tax_included');
        $data['show_on_payslip'] = $request->has('show_on_payslip');

        BenefitsDeduction::create($data);
        return redirect()->route('payroll.benefits_deductions.index')->with('success', 'Benefit/Deduction created successfully.');
    }

    public function edit(BenefitsDeduction $benefitsDeduction)
    {
        return view('benefits_deductions.edit', compact('benefitsDeduction'));
    }

    public function update(UpdateBenefitsDeductionRequest $request, BenefitsDeduction $benefitsDeduction)
    {
        $validatedData = $request->validated();

        // Convert checkbox values to boolean
        $data = $validatedData;
        $data['insurance_included'] = $request->has('insurance_included');
        $data['tax_included'] = $request->has('tax_included');
        $data['show_on_payslip'] = $request->has('show_on_payslip');
        $benefitsDeduction->update($data);

        return redirect()->route('payroll.benefits_deductions.index')->with('success', 'Benefit/Deduction updated successfully.');
    }

    public function destroy(BenefitsDeduction $benefitsDeduction)
    {
        $benefitsDeduction->delete();
        return redirect()->route('payroll.benefits_deductions.index')->with('success', 'Benefit/Deduction deleted successfully.');
    }
}
