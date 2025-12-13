<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\CustomerService;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    /**
     * Build validation rules for both store and update actions.
     * If you need per-record exceptions (e.g. unique on update),
     * use the optional $customer parameter to adjust rules.
     */
    private function rules(Request $request, ?Models\Customer $customer = null): array
    {

        return [
            'name' => [
                'required',
                'max:100',
                'string',
                'regex:/^[\w\d\s]*$/u',
                Rule::unique('customers', 'name')
                    ->where('group_id', $request->input('group_id'))
                    ->ignore(optional($customer)->id),
            ],
            'phone' => [
                'nullable',
                'numeric',
                'regex:/^09\d{9}$/',
                Rule::unique('customers', 'phone')->ignore(optional($customer)->id),
            ],
            'fax' => 'nullable|numeric',
            'address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'postal_code' => 'nullable|integer',
            'email' => 'nullable|email',
            'ecnmcs_code' => 'nullable|integer',
            'personal_code' => 'nullable|integer',
            'web_page' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'responsible' => 'nullable',
            'group_id' => 'required|exists:customer_groups,id|integer',
            'desc' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'rep_via_email' => 'nullable|in:on,off',
            'acc_name_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_bank_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_name_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_bank_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
        ];
    }

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

    public function store(Request $request)
    {
        $validatedData = $request->validate($this->rules($request));

        $validatedData['rep_via_email'] = $request->has('rep_via_email') ? 1 : 0;

        $this->service->create($validatedData);

        return redirect()->route('customers.index')->with('success', __('Customer created successfully.'));
    }

    public function edit(Models\Customer $customer)
    {
        $groups = Models\CustomerGroup::select('id', 'name')->get();

        return view('customers.edit', compact('customer', 'groups'));
    }

    public function update(Request $request, Models\Customer $customer)
    {
        $validatedData = $request->validate($this->rules($request, $customer));

        $validatedData['rep_via_email'] = $request->has('rep_via_email') ? 1 : 0;

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
