<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Checkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CheckbookController extends Controller
{
    public function index()
    {
        $checkbooks = Checkbook::with('bankAccount.bank')->withCount('cheques')->paginate(15);

        return view('checkbooks.index', compact('checkbooks'));
    }

    public function create()
    {
        return view('checkbooks.form', [
            'checkbook' => new Checkbook,
            'bankAccounts' => BankAccount::with('bank')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCheckbook($request);
        $data['company_id'] = getActiveCompany();
        $data['next_leaf_number'] ??= $data['start_leaf_number'];
        $data['is_active'] = $request->boolean('is_active', true);
        Checkbook::create($data);

        return redirect()->route('checkbooks.index')->with('success', __('cheques messages checkbook_created'));
    }

    public function edit(Checkbook $checkbook)
    {
        return view('checkbooks.form', [
            'checkbook' => $checkbook,
            'bankAccounts' => BankAccount::with('bank')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Checkbook $checkbook)
    {
        $data = $this->validateCheckbook($request, $checkbook);
        $data['is_active'] = $request->boolean('is_active');
        $data['next_leaf_number'] ??= $checkbook->next_leaf_number;
        $checkbook->update($data);

        return redirect()->route('checkbooks.index')->with('success', __('cheques messages checkbook_updated'));
    }

    public function destroy(Checkbook $checkbook)
    {
        if ($checkbook->cheques()->exists()) {
            return back()->with('error', __('cheques validation checkbook_has_cheques'));
        }
        $checkbook->delete();

        return redirect()->route('checkbooks.index')->with('success', __('cheques messages checkbook_deleted'));
    }

    private function validateCheckbook(Request $request, ?Checkbook $checkbook = null): array
    {
        foreach (['start_leaf_number', 'end_leaf_number', 'next_leaf_number'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => convertToInt($request->input($field))]);
            }
        }

        $validator = Validator::make($request->all(), [
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', getActiveCompany())],
            'title' => [
                'required',
                'string',
                'max:100',
                Rule::unique('checkbooks')->where(fn ($query) => $query->where('company_id', getActiveCompany())
                    ->where('bank_account_id', $request->input('bank_account_id')))->ignore($checkbook?->id),
            ],
            'serial_prefix' => ['nullable', 'string', 'max:20'],
            'start_leaf_number' => ['required', 'integer', 'min:1'],
            'end_leaf_number' => ['required', 'integer', 'gte:start_leaf_number'],
            'next_leaf_number' => ['nullable', 'integer', 'gte:start_leaf_number'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $checkbook) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = $request->integer('start_leaf_number');
            $end = $request->integer('end_leaf_number');
            $next = $request->filled('next_leaf_number') ? $request->integer('next_leaf_number') : ($checkbook?->next_leaf_number ?? $start);

            if ($next > $end + 1) {
                $validator->errors()->add('next_leaf_number', __('cheques validation leaf_out_of_range'));
            }

            if (! $checkbook) {
                return;
            }

            if ($checkbook->cheques()->exists() && $request->integer('bank_account_id') !== (int) $checkbook->bank_account_id) {
                $validator->errors()->add('bank_account_id', __('cheques validation checkbook_account_mismatch'));
            }

            $usedLeaves = $checkbook->cheques()->whereNotNull('checkbook_leaf_number');
            $firstUsedLeaf = (clone $usedLeaves)->min('checkbook_leaf_number');
            $lastUsedLeaf = (clone $usedLeaves)->max('checkbook_leaf_number');
            if (($firstUsedLeaf !== null && $start > $firstUsedLeaf) || ($lastUsedLeaf !== null && $end < $lastUsedLeaf)) {
                $validator->errors()->add('start_leaf_number', __('cheques validation range_excludes_used_leaves'));
            }
        });

        return $validator->validate();
    }
}
