<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonnelRequest;
use App\Http\Requests\UpdatePersonnelRequest;
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

    public function store(StorePersonnelRequest $request)
    {

        $validatedData = $request->validated();
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

    public function update(UpdatePersonnelRequest $request, Personnel $personnel)
    {
        $validatedData = $request->validated();
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
