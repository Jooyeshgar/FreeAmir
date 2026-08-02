<?php

namespace App\Http\Controllers;

use App\Enums\ChequeType;
use App\Models\BankAccount;
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
        $cheques = $this->filteredQuery($request)->with(['customer', 'endorsedTo', 'bankAccount.bank'])->latest('due_date')->paginate(20)->withQueryString();

        return view('cheques.index', [
            'cheques' => $cheques,
            'statuses' => ChequeType::statuses(),
            'directions' => ChequeType::directions(),
            'purposes' => ChequeType::purposes(),
            'accountSides' => Customer::with('group')->get(['id', 'name', 'group_id']),
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

        return redirect()->route('cheques.show', $cheque)->with('success', __('Cheque registered successfully.'));
    }

    public function show(Cheque $cheque)
    {
        $cheque->load(['customer', 'endorsedTo', 'bankAccount.bank', 'histories.user', 'histories.document', 'histories.payment']);

        return view('cheques.show', [
            'cheque' => $cheque,
            'bankAccounts' => BankAccount::with('bank')->get(),
            'accountSides' => Customer::with('group')->get(['id', 'name', 'group_id']),
        ]);
    }

    public function edit(Cheque $cheque)
    {
        abort_if($cheque->histories()->whereNotNull('from_status')->exists(), 409, __('A cheque cannot be edited after a lifecycle action. Register a replacement cheque to preserve its audit history.'));

        return view('cheques.create', [
            ...$this->formData((string) $cheque->direction->value),
            'cheque' => $cheque,
        ]);
    }

    public function update(Request $request, Cheque $cheque)
    {
        $cheque = $this->chequeService->update($cheque, $request->user(), $this->validateCheque($request, $cheque));

        return redirect()->route('cheques.show', $cheque)->with('success', __('Cheque updated successfully.'));
    }

    public function destroy(Request $request, Cheque $cheque)
    {
        $this->chequeService->delete($cheque, $request->user(), $request->filled('version') ? $request->integer('version') : null);

        return redirect()->route('cheques.index')->with('success', __('Cheque and its accounting records were deleted.'));
    }

    public function transition(Request $request, Cheque $cheque, string $action)
    {
        $cheque = $this->chequeService->transition($cheque, $request->user(), $action, $this->validateTransition($request));

        return redirect()->route('cheques.show', $cheque)->with('success', __('Cheque status updated successfully.'));
    }

    public function report(Request $request)
    {
        $query = $this->filteredQuery($request);
        $byStatus = (clone $query)->selectRaw('status, COUNT(*) as count, SUM(amount) as total')->groupBy('status')->get()
            ->mapWithKeys(fn (Cheque $row) => [$row->getRawOriginal('status') => $row]);

        $upcoming = (clone $query)->whereIn('status', [ChequeType::REGISTERED, ChequeType::DEPOSITED, ChequeType::ISSUED])->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->with('customer')->orderBy('due_date')->get();

        $overdue = (clone $query)->whereIn('status', [ChequeType::REGISTERED, ChequeType::DEPOSITED, ChequeType::ISSUED])->whereDate('due_date', '<', now()->toDateString())->sum('amount');

        return view('cheques.report', compact('byStatus', 'upcoming', 'overdue'));
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Cheque::query();
        $q = trim(toEnglish((string) $request->input('q')));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('cheque_number', 'like', "%{$q}%")
                    ->orWhere('serial', 'like', "%{$q}%")
                    ->orWhere('sayad_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$q}%"));
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
            'bankAccounts' => BankAccount::with('bank')->get(),
            'accountSides' => Customer::with('group')->get(['id', 'name', 'group_id']),
        ];
    }

    private function validateCheque(Request $request, ?Cheque $cheque = null): array
    {
        $normalized = [
            'amount' => convertToFloat($request->input('amount', 0)),
            'sayad_number' => preg_replace('/\D/', '', toEnglish((string) $request->input('sayad_number'))),
            'serial' => trim(toEnglish((string) $request->input('serial'))),
            'cheque_number' => trim(toEnglish((string) $request->input('cheque_number'))),
        ];
        foreach (['issue_date', 'due_date'] as $field) {
            if ($request->filled($field)) {
                $normalized[$field] = jalaliInputToGregorian($request->input($field), $field);
            }
        }
        $request->merge($normalized);

        $companyId = getActiveCompany();
        $validator = Validator::make(
            $request->all(),
            [
                'direction' => ['required', 'integer', Rule::in(ChequeType::directionValues())],
                'purpose' => ['required', 'integer', Rule::in(ChequeType::purposeValues())],
                'amount' => ['required', 'numeric', 'gt:0'],
                'issue_date' => ['required', 'date'],
                'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
                'sayad_number' => [
                    'required',
                    'regex:/^\d{16}$/',
                    Rule::unique('cheques', 'sayad_number')->ignore($cheque?->id),
                ],
                'cheque_number' => ['nullable', 'string', 'max:50'],
                'account_side_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
                'bank_account_id' => [
                    Rule::requiredIf($request->integer('direction') === ChequeType::PAYABLE->value),
                    'nullable',
                    Rule::exists('bank_accounts', 'id')->where('company_id', $companyId),
                ],
                'description' => ['nullable', 'string', 'max:1000'],
                'version' => ['nullable', 'integer', 'min:1'],
            ],
        );

        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
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

        return $request->validate(
            [
                'date' => ['nullable', 'date'],
                'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
                'account_side_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
                'description' => ['nullable', 'string', 'max:1000'],
                'version' => ['nullable', 'integer', 'min:1'],
            ],
        );
    }
}
