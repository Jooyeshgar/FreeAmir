<?php
namespace App\Http\Controllers;

use App\Models\PayrollPattern;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'daily_wage' => 'required|numeric',
            // Validate other fields as needed
        ]);

        PayrollPattern::create($request->all());
        return redirect()->route('payroll.payroll_patterns.index')->with('success', 'Payroll Calculation Pattern created successfully.');
    }

    public function edit(PayrollPattern $payrollPattern)
    {
        return view('payroll_patterns.edit', compact('payrollPattern'));
    }

    public function update(Request $request, PayrollPattern $payrollPattern)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'daily_wage' => 'required|numeric',
        ]);

        $payrollPattern->update($request->all());
        return redirect()->route('payroll.payroll_patterns.index')->with('success', 'Payroll Calculation Pattern updated successfully.');
    }

    public function destroy(PayrollPattern $payrollPattern)
    {
        $payrollPattern->delete();
        return redirect()->route('payroll.payroll_patterns.index')->with('success', 'Payroll Calculation Pattern deleted successfully.');
    }
}
