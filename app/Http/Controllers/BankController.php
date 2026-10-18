<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\FiscalYearMutationGuard;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __construct(private readonly FiscalYearMutationGuard $fiscalYearMutationGuard) {}

    public function index()
    {
        $banks = Models\Bank::paginate(12);

        return view('banks.index', compact('banks'));
    }

    public function create()
    {
        return view('banks.create');
    }

    public function store(Request $request)
    {
        $this->fiscalYearMutationGuard->ensureOpen();

        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
        ]);

        $validatedData['fiscal_year_id'] = getActiveFiscalYear();
        Models\Bank::create($validatedData);

        return redirect()->route('banks.index')->with('success', __('Bank created successfully.'));
    }

    public function edit(Models\Bank $bank)
    {
        return view('banks.edit', compact('bank'));
    }

    public function update(Request $request, Models\Bank $bank)
    {
        $this->fiscalYearMutationGuard->ensureOpen();

        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
        ]);

        $bank->update($validatedData);

        return redirect()->route('banks.index')->with('success', __('Bank updated successfully.'));
    }

    public function destroy(Models\Bank $bank)
    {
        $this->fiscalYearMutationGuard->ensureOpen();
        $bank->delete();

        return redirect()->route('banks.index')->with('success', __('Bank deleted successfully.'));
    }
}
