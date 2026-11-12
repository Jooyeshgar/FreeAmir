<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Models;
use App\Models\Bank;
use App\Models\FiscalYear;
use App\Services\FiscalYearMutationGuard;
use App\Services\SubjectService;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function index()
    {
        $bankAccounts = Models\BankAccount::with('bank')->paginate(12);

        return view('bankAccounts.index', compact('bankAccounts'))->with('fiscalYearClosed', FiscalYear::find(getActiveFiscalYear())?->isClosed() ?? false);
    }

    public function create()
    {
        $banks = Bank::select('id', 'name')->limit(20)->get();

        return view('bankAccounts.create', compact('banks'));
    }

    public function store(StoreBankAccountRequest $request)
    {
        app(FiscalYearMutationGuard::class)->ensureOpen();

        $validatedData = $request->validated();

        $bankAccount = Models\BankAccount::create($validatedData);

        $bankSubject = $this->subjectService->createSubject([
            'name' => $bankAccount->name,
            'parent_id' => config('amir.bank'),
        ]);

        $bankAccount->subject()->save($bankSubject);

        $bankAccount->subject_id = $bankSubject->id;
        $bankAccount->saveQuietly();

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account created successfully.'));
    }

    public function show(Models\BankAccount $bankAccount)
    {
        return view('bankAccounts.show', compact('bankAccount'))->with('fiscalYearClosed', FiscalYear::find(getActiveFiscalYear())?->isClosed() ?? false);
    }

    public function edit(Models\BankAccount $bankAccount)
    {
        $bankIdsForSelect = Bank::select('id', 'name')->limit(20)->pluck('id');
        $oldBank = $bankAccount->bank;
        $banks = Bank::whereIn('id', $bankIdsForSelect->push($oldBank->id)->unique())->get();

        return view('bankAccounts.edit', compact('bankAccount', 'banks'));
    }

    public function update(StoreBankAccountRequest $request, Models\BankAccount $bankAccount)
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($bankAccount);

        $validatedData = $request->validated();

        $bankAccount->update($validatedData);

        $bankAccount->subject->update(['name' => $validatedData['name']]);

        return redirect()->route('bank-accounts.index')->with('success', __('Bank Account updated successfully.'));
    }

    public function destroy(Models\BankAccount $bankAccount)
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($bankAccount);

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
