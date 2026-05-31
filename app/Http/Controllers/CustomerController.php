<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomerImportException;
use App\Http\Requests\StoreCustomerRequest;
use App\Models;
use App\Services\CustomerImportService;
use App\Services\CustomerService;
use App\Services\SubjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request)
    {
        $query = Models\Customer::with('subject', 'group')->withCount('comments')->orderBy('id', 'desc');

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
                    ->orWhere('cell', 'like', '%'.$phone.'%');
            });
        }

        $groupId = $request->query('group_id');

        if ($groupId && $groupId !== 'all') {
            $query->where('group_id', $groupId);
        }

        $customers = $query->paginate(30)->appends($request->query());

        $groups = Models\CustomerGroup::select('id', 'name')->orderBy('name')->get();

        return view('customers.index', compact('customers', 'groups', 'groupId'));
    }

    public function create()
    {
        $groups = Models\CustomerGroup::select('id', 'name')->get();

        return view('customers.create', compact('groups'));
    }

    public function store(StoreCustomerRequest $request)
    {
        $validatedData = $request->validated();

        $this->service->create($validatedData);

        return redirect()->route('customers.index')->with('success', __('Customer created successfully.'));
    }

    public function edit(Models\Customer $customer)
    {
        $groups = Models\CustomerGroup::select('id', 'name')->get();

        return view('customers.edit', compact('customer', 'groups'));
    }

    public function update(StoreCustomerRequest $request, Models\Customer $customer)
    {
        $validatedData = $request->validated();

        $this->service->update($customer, $validatedData);

        return redirect()->route('customers.index')->with('success', __('Customer updated successfully.'));
    }

    public function destroy(Models\Customer $customer)
    {
        try {
            $this->service->delete($customer);

            return redirect()->route('customers.index')->with('success', __('Customer deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('customers.index')->with('error', $e->getMessage());
        }
    }

    public function show(Models\Customer $customer)
    {
        $customer->load(['group', 'subject', 'comments.commentBy']);
        $subjectBalance = $customer->subject
            ? SubjectService::sumSubject($customer->subject->id)
            : 0;

        $orders = Models\Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('customers.show', compact('customer', 'orders', 'subjectBalance'));
    }

    public function export(): StreamedResponse
    {
        $filename = 'customers_'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM so Excel reads Persian text correctly.
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, CustomerImportService::COLUMNS);

            Models\Customer::with('group', 'subject')
                ->orderBy('id')
                ->chunk(200, function ($customers) use ($file) {
                    foreach ($customers as $customer) {
                        fputcsv($file, [
                            $customer->name,
                            $customer->group?->name,
                            $customer->subject?->code,
                            $customer->type?->value,
                            $customer->phone,
                            $customer->cell,
                            $customer->fax,
                            $customer->address,
                            $customer->postal_code,
                            $customer->email,
                            $customer->ecnmcs_code,
                            $customer->personal_code,
                            $customer->web_page,
                            $customer->responsible,
                            $customer->connector,
                            $customer->desc,
                            $customer->credit,
                            $customer->disc_rate,
                            $customer->acc_name_1,
                            $customer->acc_no_1,
                            $customer->acc_bank_1,
                            $customer->acc_name_2,
                            $customer->acc_no_2,
                            $customer->acc_bank_2,
                        ]);
                    }
                });

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importForm(): View
    {
        return view('customers.import');
    }

    public function import(Request $request, CustomerImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $result = $importService->import($request->file('file'), getActiveCompany());
        } catch (CustomerImportException $e) {
            return redirect()->route('customers.import')->with('error', $e->getMessage());
        }

        return redirect()->route('customers.index')->with('success', __('Import complete: :imported customers imported, :groups groups created.', [
            'imported' => $result['imported'],
            'groups' => $result['groups_created'],
        ]));
    }
}
