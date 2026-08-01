<?php

namespace App\Http\Controllers;

use App\Enums\ChequeType;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Checkbook;
use App\Models\Cheque;
use App\Models\Customer;
use App\Services\ChequeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChequeController extends Controller
{
    public function __construct(private readonly ChequeService $chequeService) {}

    public function index(Request $request)
    {
        $cheques = $this->filteredQuery($request)
            ->with(['party', 'endorsedTo', 'bank', 'bankAccount'])
            ->latest('due_date')
            ->paginate(20)
            ->withQueryString();

        return view('cheques.index', [
            'cheques' => $cheques,
            'statuses' => ChequeType::statuses(),
            'directions' => ChequeType::directions(),
            'purposes' => ChequeType::purposes(),
            'parties' => Customer::orderBy('name')->limit(100)->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        return view('cheques.create', [
            ...$this->formData($request->input('direction')),
            'cheque' => null,
        ]);
    }

    public function store(Request $request)
    {
        $cheque = $this->chequeService->register($request->user(), $this->validateCheque($request));

        return redirect()->route('cheques.show', $cheque)->with('success', __('cheques messages created'));
    }

    public function show(Cheque $cheque)
    {
        $cheque->load([
            'party', 'endorsedTo', 'bank', 'bankAccount.bank', 'checkbook', 'creator',
            'histories.actor', 'histories.document', 'histories.payment', 'histories.revertedBy',
        ]);

        return view('cheques.show', [
            'cheque' => $cheque,
            'bankAccounts' => BankAccount::with('bank')->orderBy('name')->get(),
            'parties' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Cheque $cheque)
    {
        abort_if($cheque->histories()->whereNotNull('from_status')->exists(), 409, __('cheques validation update_after_transition'));

        return view('cheques.create', [
            ...$this->formData((string) $cheque->direction->value),
            'cheque' => $cheque,
        ]);
    }

    public function update(Request $request, Cheque $cheque)
    {
        $cheque = $this->chequeService->update($cheque, $request->user(), $this->validateCheque($request, $cheque));

        return redirect()->route('cheques.show', $cheque)->with('success', __('cheques messages updated'));
    }

    public function destroy(Request $request, Cheque $cheque)
    {
        $this->chequeService->delete($cheque, $request->user(), $request->filled('version') ? $request->integer('version') : null);

        return redirect()->route('cheques.index')->with('success', __('cheques messages deleted'));
    }

    public function transition(Request $request, Cheque $cheque, string $action)
    {
        $this->chequeService->transition($cheque, $request->user(), $action, $this->validateTransition($request));

        return redirect()->route('cheques.show', $cheque)->with('success', __('cheques messages transitioned'));
    }

    public function report(Request $request)
    {
        $query = $this->filteredQuery($request);
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (Cheque $row) => [$row->getRawOriginal('status') => $row]);

        $upcoming = (clone $query)
            ->whereIn('status', [ChequeType::REGISTERED, ChequeType::DEPOSITED, ChequeType::ISSUED])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->with('party')
            ->orderBy('due_date')
            ->get();

        $overdue = (clone $query)
            ->whereIn('status', [ChequeType::REGISTERED, ChequeType::DEPOSITED, ChequeType::ISSUED])
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('amount');

        return view('cheques.report', compact('byStatus', 'upcoming', 'overdue'));
    }

    public function calendar(Request $request)
    {
        $from = $request->filled('from') ? jalaliInputToGregorian($request->input('from'), 'from') : now()->startOfMonth()->toDateString();
        $to = $request->filled('to') ? jalaliInputToGregorian($request->input('to'), 'to') : now()->endOfMonth()->toDateString();
        $cheques = Cheque::with('party')->whereBetween('due_date', [$from, $to])->orderBy('due_date')->get();

        return view('cheques.calendar', compact('cheques', 'from', 'to'));
    }

    public function print(Cheque $cheque)
    {
        abort_unless($cheque->direction === ChequeType::PAYABLE, 404);
        $cheque->load(['party', 'bankAccount.bank', 'checkbook']);

        return view('cheques.print', compact('cheque'));
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Cheque::query();
        $q = trim(toEnglish((string) $request->input('q')));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('serial', 'like', "%{$q}%")
                    ->orWhere('sayad_number', 'like', "%{$q}%")
                    ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'like', "%{$q}%"));
            });
        }

        foreach (['direction', 'purpose', 'status', 'customer_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', convertToFloat($request->input('amount_min')));
        }
        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', convertToFloat($request->input('amount_max')));
        }
        if ($request->filled('due_from')) {
            $query->whereDate('due_date', '>=', jalaliInputToGregorian($request->input('due_from'), 'due_from'));
        }
        if ($request->filled('due_to')) {
            $query->whereDate('due_date', '<=', jalaliInputToGregorian($request->input('due_to'), 'due_to'));
        }

        return $query;
    }

    private function formData(?string $direction = null): array
    {
        return [
            'selectedDirection' => ChequeType::tryFrom((int) $direction) ?? ChequeType::RECEIVABLE,
            'banks' => Bank::orderBy('name')->get(),
            'bankAccounts' => BankAccount::with('bank')->orderBy('name')->get(),
            'checkbooks' => Checkbook::with('bankAccount')->where('is_active', true)->orderBy('title')->get(),
            'parties' => Customer::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validateCheque(Request $request, ?Cheque $cheque = null): array
    {
        $normalized = [
            'amount' => convertToFloat($request->input('amount', 0)),
            'sayad_number' => preg_replace('/\D/', '', toEnglish((string) $request->input('sayad_number'))),
            'serial' => trim(toEnglish((string) $request->input('serial'))),
        ];
        foreach (['issue_date', 'due_date'] as $field) {
            if ($request->filled($field)) {
                $normalized[$field] = jalaliInputToGregorian($request->input($field), $field);
            }
        }
        if ($request->filled('checkbook_leaf_number')) {
            $normalized['checkbook_leaf_number'] = convertToInt($request->input('checkbook_leaf_number'));
        }
        $request->merge($normalized);

        $companyId = getActiveCompany();
        $validator = Validator::make($request->all(), [
            'direction' => ['required', 'integer', Rule::in(ChequeType::directionValues())],
            'purpose' => ['required', 'integer', Rule::in(ChequeType::purposeValues())],
            'amount' => ['required', 'numeric', 'gt:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'serial' => ['nullable', 'required_without:checkbook_leaf_number', 'string', 'max:50'],
            'sayad_number' => [
                'required',
                'regex:/^\d{16}$/',
                Rule::unique('cheques', 'sayad_number')->ignore($cheque?->id),
            ],
            'party_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'bank_id' => ['required', Rule::exists('banks', 'id')->where('company_id', $companyId)],
            'bank_account_id' => [
                Rule::requiredIf($request->integer('direction') === ChequeType::PAYABLE->value),
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('company_id', $companyId),
            ],
            'checkbook_id' => ['nullable', Rule::exists('checkbooks', 'id')->where('company_id', $companyId)],
            'checkbook_leaf_number' => ['nullable', 'integer', 'min:1'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'branch_city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $account = $request->filled('bank_account_id') ? BankAccount::find($request->integer('bank_account_id')) : null;
            if ($account && (int) $account->bank_id !== $request->integer('bank_id')) {
                $validator->errors()->add('bank_account_id', __('cheques validation account_bank_mismatch'));
            }

            if ($request->filled('checkbook_id')) {
                $checkbook = Checkbook::find($request->integer('checkbook_id'));
                $leaf = $request->integer('checkbook_leaf_number');
                if (! $checkbook || (int) $checkbook->bank_account_id !== $request->integer('bank_account_id')) {
                    $validator->errors()->add('checkbook_id', __('cheques validation checkbook_account_mismatch'));
                } elseif (! $leaf || ! $checkbook->contains($leaf)) {
                    $validator->errors()->add('checkbook_leaf_number', __('cheques validation leaf_out_of_range'));
                }
                if ($request->integer('direction') !== ChequeType::PAYABLE->value) {
                    $validator->errors()->add('checkbook_id', __('cheques validation checkbook_payable_only'));
                }
            }
        });

        return $validator->validate();
    }

    private function validateTransition(Request $request): array
    {
        if ($request->filled('date')) {
            $request->merge(['date' => jalaliInputToGregorian($request->input('date'), 'date')]);
        }

        $companyId = getActiveCompany();

        return $request->validate([
            'date' => ['nullable', 'date'],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'party_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
