<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollPatternRequest;
use App\Http\Requests\UpdatePayrollPatternRequest;
use App\Models\PayrollPattern;

class PayrollPatternController extends Controller
{
    public function index()
    {
        $payrollPatterns = PayrollPattern::paginate(10);
        return view('payroll_patterns.index', compact('payrollPatterns'));
    }

    public function create()
    {
        return view('payroll_patterns.create');
    }

    public function store(StorePayrollPatternRequest $request)
    {
        $validatedData = $request->validated();
        PayrollPattern::create($validatedData);
        return redirect()->route('payroll.payroll_patterns.index')->with('success', 'Payroll Calculation Pattern created successfully.');
    }

    public function edit(PayrollPattern $payrollPattern)
    {
        return view('payroll_patterns.edit', compact('payrollPattern'));
    }

    public function update(UpdatePayrollPatternRequest $request, PayrollPattern $payrollPattern)
    {
        $validatedData = $request->validated();
        $payrollPattern->update($validatedData);
        return redirect()->route('payroll.payroll_patterns.index')->with('success', 'Payroll Calculation Pattern updated successfully.');
    }

    public function destroy(PayrollPattern $payrollPattern)
    {
        $payrollPattern->delete();
        return redirect()->route('payroll.payroll_patterns.index')->with('success', 'Payroll Calculation Pattern deleted successfully.');
    }
}
