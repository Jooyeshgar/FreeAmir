<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\CustomerImportService;
use App\Services\CustomerService;
use App\Services\ReportExportService;
use App\Services\SubjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request)
    {
        $query = Customer::with('subject', 'group')
            ->withCount('comments')
            ->select('customers.*')
            ->selectSub(
                Transaction::query()
                    ->selectRaw('COALESCE(SUM(value), 0)')
                    ->whereColumn('transactions.subject_id', 'customers.subject_id'),
                'balance'
            )
            ->orderBy('id', 'desc');

        if (request()->has('name') && request('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        if (request()->has('subject_code') && request('subject_code')) {
            $subjectCode = request('subject_code');
            if (str_contains($subjectCode, '/')) {
                $subjectCode = str_replace('/', '', $subjectCode);
            }

            $query->whereHas('subject', function ($subject) use ($subjectCode) {
                $subject->where('code', 'like', '%'.$subjectCode.'%');
            });
        }

        if (request()->has('phone') && request('phone')) {
            $phone = request('phone');
            $query->where(function ($q) use ($phone) {
                $q->where('phone', 'like', '%'.$phone.'%')
                    ->orWhere('mobile', 'like', '%'.$phone.'%');
            });
        }

        $groupId = $request->query('group_id');

        if ($groupId && $groupId !== 'all') {
            $query->where('group_id', $groupId);
        }

        $balanceFilter = $request->query('balance', 'all');
        if (! in_array($balanceFilter, ['all', 'debt', 'credit'], true)) {
            $balanceFilter = 'all';
        }
        if ($balanceFilter !== 'all') {
            $query->whereIn('subject_id', $this->balanceSubjectIds($balanceFilter));
            $query->reorder('balance', $balanceFilter === 'debt' ? 'asc' : 'desc');
        }

        $balanceSum = (float) Transaction::query()
            ->whereIn('subject_id', (clone $query)->reorder()->whereNotNull('subject_id')->select('subject_id'))
            ->sum('value');

        $customers = $query->paginate(30)->appends($request->query());

        $groups = CustomerGroup::select('id', 'name')->orderBy('name')->get();

        return view('customers.index', compact('customers', 'groups', 'groupId', 'balanceFilter', 'balanceSum'));
    }

    private function balanceSubjectIds(string $balanceFilter)
    {
        $customerSubjectIds = Customer::query()->whereNotNull('subject_id')->pluck('subject_id');
        $comparison = $balanceFilter === 'credit' ? 'SUM(value) > 0' : 'SUM(value) < 0';

        return Transaction::query()
            ->whereIn('subject_id', $customerSubjectIds)
            ->groupBy('subject_id')
            ->havingRaw($comparison)
            ->pluck('subject_id');
    }

    public function create()
    {
        $groups = CustomerGroup::select('id', 'name')->get();

        return view('customers.create', compact('groups'));
    }

    public function store(StoreCustomerRequest $request)
    {
        $validatedData = $request->validated();

        $this->service->create($validatedData);

        return redirect()->route('customers.index')->with('success', __('Customer created successfully.'));
    }

    public function edit(Customer $customer)
    {
        $groups = CustomerGroup::select('id', 'name')->get();

        return view('customers.edit', compact('customer', 'groups'));
    }

    public function update(StoreCustomerRequest $request, Customer $customer)
    {
        $validatedData = $request->validated();

        $this->service->update($customer, $validatedData);

        return redirect()->route('customers.index')->with('success', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer)
    {
        try {
            $this->service->delete($customer);

            return redirect()->route('customers.index')->with('success', __('Customer deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('customers.index')->with('error', $e->getMessage());
        }
    }

    public function show(Customer $customer)
    {
        $customer->load(['group', 'subject', 'comments.commentBy']);
        $subjectBalance = $customer->subject
            ? SubjectService::sumSubject($customer->subject->id)
            : 0;

        $orders = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'orders_page')
            ->withQueryString();

        $cheques = Cheque::query()
            ->with(['customer', 'endorsedTo', 'bankAccount.bank'])
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere('endorsed_to_id', $customer->id);
            })
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'cheques_page')
            ->withQueryString();

        return view('customers.show', compact('customer', 'orders', 'cheques', 'subjectBalance'));
    }

    public function export(ReportExportService $reportExportService): StreamedResponse
    {
        return $reportExportService->downloadResponse('customers_csv', []);
    }

    public function importForm(): View
    {
        return view('customers.import');
    }

    public function import(ImportCustomerRequest $request, CustomerImportService $importService): RedirectResponse
    {
        try {
            $result = $importService->import($request->file('file'), getActiveCompany());
        } catch (ValidationException $e) {
            return redirect()->route('customers.import')->with('error', $e->getMessage());
        }

        return redirect()->route('customers.index')->with('success', __('Import complete: :imported customers imported, :updated updated, :groups groups created.', [
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'groups' => $result['groups_created'],
        ]));
    }
}
