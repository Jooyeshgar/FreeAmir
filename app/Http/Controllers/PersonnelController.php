<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Bank;
use App\Models\OrganizationalChart;
use App\Models\PayrollPattern;
use App\Models\SalarySlip;
use App\Models\Workhouse;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    public function index()
    {
        $personnelRecords = Personnel::paginate(10); // Make sure this matches the variable name in the view
        return view('personnel.index', compact('personnelRecords'));
    }

    public function create()
    {
        $salarySlips = SalarySlip::all(); // Assuming SalarySlip model exists
        $selectedSalarySlips = null; // Default to null for new forms (single select by default)

        $banks = Bank::all();
        $organizationalCharts = OrganizationalChart::all();
        $workhouses = Workhouse::all();

        return view('personnel.create', compact('salarySlips', 'selectedSalarySlips', 'banks', 'organizationalCharts', 'workhouses'));
    }

    public function store(Request $request)
    {
        dd($request->input('salary_slips', []));

        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'personnel_code' => 'required|unique:personnel',
            'father_name' => 'required',
            'nationality' => 'required|in:iranian,non_iranian',
            'identity_number' => 'required',
            'national_code' => 'required',
            'passport_number' => 'required',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'gender' => 'required|in:female,male,other',
            'contact_number' => 'required',
            'address' => 'required',
            'insurance_number' => 'required',
            'insurance_type' => 'required|in:social_security,other',
            'children_count' => 'nullable|integer',
            'bank_id' => 'required|exists:banks,id',
            'account_number' => 'required',
            'card_number' => 'required',
            'iban' => 'required',
            'detailed_code' => 'required',
            'contract_start_date' => 'nullable|date',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'contract_type' => 'required|in:official,contract,other',
            'birth_place' => 'nullable',
            'organizational_chart_id' => 'required|exists:organizational_charts,id',
            'military_status' => 'required|in:not_subject,completed,in_progress',
            'workhouse_id' => 'required|exists:workhouses,id',
        ]);

        $personnel = Personnel::create($validatedData);
        $personnel->salarySlips()->sync($request->input('salary_slips', []));

        return redirect()->route('payroll.personnel.index')->with('success', 'Personnel created successfully');
    }

    public function show(Personnel $personnel)
    {
        return view('personnel.show', compact('personnel'));
    }

    public function edit(Personnel $personnel)
    {
        $banks = Bank::all();
        $organizationalCharts = OrganizationalChart::all();
        $workhouses = Workhouse::all();

        $salarySlips = SalarySlip::all();
        $selectedSalarySlips = $personnel->salary_slips; // Fetch the selected values (can be a single value or an array)

        return view('personnel.edit', compact('selectedSalarySlips', 'salarySlips', 'personnel', 'banks', 'organizationalCharts', 'workhouses'));
    }

    public function update(Request $request, Personnel $personnel)
    {
        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'personnel_code' => 'required|unique:personnel,personnel_code,' . $personnel->id,
            'father_name' => 'required',
            'nationality' => 'required|in:iranian,non_iranian',
            'identity_number' => 'required',
            'national_code' => 'required',
            'passport_number' => 'required',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'gender' => 'required|in:female,male,other',
            'contact_number' => 'required',
            'address' => 'required',
            'insurance_number' => 'required',
            'insurance_type' => 'required|in:social_security,other',
            'children_count' => 'nullable|integer',
            'bank_id' => 'required|exists:banks,id',
            'account_number' => 'required',
            'card_number' => 'required',
            'iban' => 'required',
            'detailed_code' => 'required',
            'contract_start_date' => 'nullable|date',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'contract_type' => 'required|in:official,contract,other',
            'birth_place' => 'nullable',
            'organizational_chart_id' => 'required|exists:organizational_charts,id',
            'military_status' => 'required|in:not_subject,completed,in_progress',
            'workhouse_id' => 'required|exists:workhouses,id',
        ]);

        $personnel->update($validatedData);
        $personnel->salarySlips()->sync($request->input('salary_slips', []));

        return redirect()->route('payroll.personnel.index')->with('success', 'Personnel updated successfully');
    }

    public function destroy(Personnel $personnel)
    {
        $personnel->delete();
        return redirect()->route('payroll.personnel.index')->with('success', 'Personnel deleted successfully');
    }
}
