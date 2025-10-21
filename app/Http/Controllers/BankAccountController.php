<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $bankAccounts = Models\BankAccount::with('bank')->paginate(12);

        return view('bankAccounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        $banks = Models\Bank::select('id', 'name')->get();

        return view('bankAccounts.create', compact('banks'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'number' => 'required|numeric',
            'type' => 'required|integer|regex:/^[\w\d\s]*$/u',
            'owner' => 'nullable|string|regex:/^[\w\d\s]*$/u',
            'bank_id' => 'required|exists:banks,id|integer',
            'bank_branch' => 'nullable|string|regex:/^[\w\d\s]*$/u',
            'bank_address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'bank_phone' => 'nullable|numeric',
            'bank_web_page' => 'nullable|string|regex:/^[\w\d\s]*$/u',
            'desc' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        ]);

        Models\BankAccount::create($validatedData);

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account created successfully.'));
    }

    public function edit(Models\BankAccount $bankAccount)
    {
        $banks = Models\Bank::select('id', 'name')->get();

        return view('bankAccounts.edit', compact('bankAccount', 'banks'));
    }

    public function update(Request $request, Models\BankAccount $bankAccount)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20',
            'number' => 'required|numeric',
            'type' => 'required|integer|regex:/^[\w\d\s]*$/u',
            'owner' => 'required|string|regex:/^[\w\d\s]*$/u',
            'bank_id' => 'required|exists:banks,id|integer',
            'bank_branch' => 'nullable|string|regex:/^[\w\d\s]*$/u',
            'bank_address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'bank_phone' => 'nullable|numeric',
            'bank_web_page' => 'nullable|string|regex:/^[\w\d\s]*$/u',
            'desc' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        ]);

        $bankAccount->update($validatedData);

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account updated successfully.'));
    }

    public function destroy(Models\BankAccount $bankAccount)
    {
        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account deleted successfully.'));
    }
}
