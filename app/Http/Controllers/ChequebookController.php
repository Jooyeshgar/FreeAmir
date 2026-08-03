<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Chequebook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChequebookController extends Controller
{
    public function index()
    {
        $chequebooks = Chequebook::query()->with('bankAccount.bank')->withCount('cheques')->latest()->paginate(20);

        return view('cheques.chequebooks.index', compact('chequebooks'));
    }

    public function create()
    {
        return view('cheques.chequebooks.form', [
            'chequebook' => null,
            'bankAccounts' => BankAccount::with('bank')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $chequebook = Chequebook::create([
            ...$this->validatedData($request),
            'company_id' => getActiveCompany(),
        ]);

        return redirect()->route('cheques.chequebooks.show', $chequebook)->with('success', __('Chequebook created successfully.'));
    }

    public function show(Chequebook $chequebook)
    {
        $chequebook->load('bankAccount.bank');
        $cheques = $chequebook->cheques()->with('customer')->latest('due_date')->paginate(20);

        return view('cheques.chequebooks.show', compact('chequebook', 'cheques'));
    }

    public function edit(Chequebook $chequebook)
    {
        return view('cheques.chequebooks.form', [
            'chequebook' => $chequebook,
            'bankAccounts' => BankAccount::with('bank')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Chequebook $chequebook)
    {
        $chequebook->update($this->validatedData($request, $chequebook));

        return redirect()->route('cheques.chequebooks.show', $chequebook)->with('success', __('Chequebook updated successfully.'));
    }

    public function destroy(Chequebook $chequebook)
    {
        $chequebook->delete();

        return redirect()->route('cheques.chequebooks.index')->with('success', __('Chequebook deleted successfully. Associated cheques were preserved.'));
    }

    private function validatedData(Request $request, ?Chequebook $chequebook = null): array
    {
        foreach (['first_leaf', 'last_leaf', 'next_leaf'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => toEnglish($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', getActiveCompany())],
            'serial_prefix' => ['nullable', 'string', 'max:50'],
            'first_leaf' => ['required', 'integer', 'min:0'],
            'last_leaf' => ['required', 'integer', 'gte:first_leaf'],
            'next_leaf' => ['nullable', 'integer', 'gte:first_leaf', 'lte:last_leaf'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($chequebook && (int) $validated['bank_account_id'] !== (int) $chequebook->bank_account_id && $chequebook->cheques()->exists()) {
            throw ValidationException::withMessages([
                'bank_account_id' => __('The bank account cannot be changed while the chequebook has associated cheques.'),
            ]);
        }

        return [
            'bank_account_id' => $validated['bank_account_id'],
            'serial_prefix' => $validated['serial_prefix'] ?? null,
            'first_leaf' => $validated['first_leaf'],
            'last_leaf' => $validated['last_leaf'],
            'next_leaf' => $validated['next_leaf'] ?? $validated['first_leaf'],
            'desc' => $validated['description'] ?? null,
        ];
    }
}
