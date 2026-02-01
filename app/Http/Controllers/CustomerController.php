<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models;
use App\Services\CustomerService;
use App\Services\SubjectService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request)
    {
        $groupId = $request->query('group_id');

        $query = Models\Customer::with('subject', 'group')->orderBy('id', 'desc');

        if ($groupId && $groupId !== 'all') {
            $query->where('group_id', $groupId);
        }

        $customers = $query->paginate(30)->appends(['group_id' => $groupId]);

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
        $customer->load(['group', 'subject']);
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
}
