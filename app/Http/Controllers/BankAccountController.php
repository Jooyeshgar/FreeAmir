<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $bankAccounts = Models\BankAccount::with('bank')->paginate(12);
        $cols = [
            'name', 'number',
            'type', 'owner',
            'bank_id', 'bank_branch', 'bank_address',
            'bank_phone', 'bank_web_page', 'desc'
        ];
        return view('bankAccounts.index', compact('bankAccounts', 'cols'));
    }

    public function create()
    {
        $banks = Models\Bank::select('id', 'name')->get();
        $fieldset = $this->fieldset($banks);
        return view('bankAccounts.create', compact('fieldset'));
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'name' => 'required|max:20',
            'number' => 'required',
            'type' => 'nullable',
            'owner' => 'required',
            'bank_id' => 'required',
            'bank_branch' => 'required',
            'bank_address' => 'required',
            'bank_phone' => 'required',
            'bank_web_page' => 'required',
            'desc' => 'required'
        ]);

        Models\BankAccount::create($validatedData);

        return redirect()->route('bank-accounts.index')->with('success', 'Bank Account created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit(Models\BankAccount $bankAccount)
    {
        $banks = Models\Bank::select('id', 'name')->get();
        $fieldset = $this->fieldset($banks);
        return view('bankAccounts.edit', compact('bankAccount', 'fieldset'));
    }

    public function update(Request $request, Models\BankAccount $bankAccount)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'name' => 'required|max:20',
            'number' => 'required',
            'type' => 'nullable',
            'owner' => 'required',
            'bank_id' => 'required',
            'bank_branch' => 'required',
            'bank_address' => 'required',
            'bank_phone' => 'required',
            'bank_web_page' => 'required',
            'desc' => 'required'
        ]);

        $bankAccount->update($validatedData);

        return redirect()->route('bank-accounts.index')->with('success', 'Bank Account updated successfully.');
    }

    public function destroy(Models\BankAccount $bankAccount)
    {
        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')->with('success', 'Bank Account deleted successfully.');
    }

    public function fieldset($banks): array
    {
        return [
            'حساب' => [
                'name' => ['label' => 'نام', 'type' => 'text'],
                'number' => ['label' => 'شماره', 'type' => 'number'],
                'type' => ['label' => 'نوع', 'type' => 'text'],
                'owner' => ['label' => 'صاحب', 'type' => 'text'],
                'desc' => ['label' => 'توضیح', 'type' => 'textarea'],
            ],
            'بانک' => [
                'bank_id' => ['label' => 'بانک', 'type' => 'select', 'options' => $banks],
                'bank_branch' => ['label' => 'شعبه', 'type' => 'text'],
                'bank_address' => ['label' => 'نشانی', 'type' => 'textarea'],
                'bank_phone' => ['label' => 'تلفن', 'type' => 'number'],
                'bank_web_page' => ['label' => 'صفحه وب', 'type' => 'text'],
            ],
        ];
    }
}
