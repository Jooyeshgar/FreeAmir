<?php

namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:benefit,deduction',
            'calculation' => 'required|in:fixed,hourly,manual',
            'amount' => 'required|numeric',
        ]);

        // Convert checkbox values to boolean
        $data = $request->only([
            'name',
            'type',
            'calculation',
            'amount'
        ]);
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

    public function update(Request $request, BenefitsDeduction $benefitsDeduction)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:benefit,deduction',
            'calculation' => 'required|in:fixed,hourly,manual',
            'amount' => 'required|numeric',
        ]);

        // Convert checkbox values to boolean
        $data = $request->only([
            'name',
            'type',
            'calculation',
            'amount'
        ]);
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
