<?php

namespace App\Http\Controllers;

use App\Models;
use App\Models\Bank;
use App\Services\SubjectService;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function index()
    {
        $bankAccounts = Models\BankAccount::with('bank')->paginate(12);

        return view('bankAccounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        $banks = Models\Bank::select('id', 'name')->limit(20)->get();

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

        $bankAccount = Models\BankAccount::create($validatedData);

        $bankSubject = $this->subjectService->createSubject([
            'name' => $bankAccount->name.' - '.$bankAccount->bank->name,
            'parent_id' => config('amir.bank'),
        ]);

        $bankAccount->subject()->save($bankSubject);

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account created successfully.'));
    }

    public function edit(Models\BankAccount $bankAccount)
    {
        $bankIdsForSelect = Models\Bank::select('id', 'name')->limit(20)->pluck('id');
        $oldBank = $bankAccount->bank;
        $banks = Models\Bank::whereIn('id', $bankIdsForSelect->push($oldBank->id)->unique())->get();

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

        $bankAccount->subject->update([
            'name' => $validatedData['name'].' - '.$bankAccount->bank->name,
        ]);

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account updated successfully.'));
    }

    public function destroy(Models\BankAccount $bankAccount)
    {
        $bankAccount->delete();
        $bankAccount->subject->delete();

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account deleted successfully.'));
    }

    public function searchBank(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $banks = Bank::where('name', 'like', "%{$q}%")->select('id', 'name')->limit(20)->get();

        if ($banks->isEmpty()) {
            return response()->json([]);
        }

        $options = (object) [
            0 => $banks->map(fn (Bank $bank) => [
                'id' => $bank->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $bank->name,
                'type' => 'bank',
            ])->all(),
        ];

        return response()->json([
            [
                'id' => 'group_banks',
                'headerGroup' => 'bank',
                'options' => $options,
            ],
        ]);
    }
}
