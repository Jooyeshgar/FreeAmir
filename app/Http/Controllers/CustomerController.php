<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Models\Customer::with('subject', 'group')->paginate(12);
        $cols = [
            'code', 'name',
            'phone', 'fax', 'address',
            'postal_code', 'email', 'ecnmcs_code', 'personal_code',
            'web_page', 'responsible', 'connector', 'group_id', 'desc'
        ];
        return view('customers.index', compact('customers', 'cols'));
    }

    public function create()
    {
        $group = Models\CustomerGroup::select('id', 'name')->get();
        $fieldset = $this->fieldset($group);
        return view('customers.create', compact('fieldset'));
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|unique:customers,code',
            'name' => 'required|max:20',
            'phone' => 'required',
            'cell' => 'required',
            'fax' => 'required',
            'address' => 'required',
            'postal_code' => 'required',
            'email' => 'required',
            'ecnmcs_code' => 'required',
            'personal_code' => 'required',
            'web_page' => 'required',
            'responsible' => 'required',
            'connector' => 'required',
            'group_id' => 'required',
            'desc' => 'required',
            'rep_via_email' => 'nullable',
            'acc_name_1' => 'required',
            'acc_no_1' => 'required',
            'acc_bank_1' => 'required',
            'acc_name_2' => 'required',
            'acc_no_2' => 'required',
            'acc_bank_2' => 'required',
            'type_buyer' => 'nullable',
            'type_seller' => 'nullable',
            'type_mate' => 'nullable',
            'type_agent' => 'nullable',
            'commission' => 'required',
            'marked' => 'nullable',
            'reason' => 'required',
            'disc_rate' => 'required',
            'balance'=>'required',
            'credit'=>'required',
        ]);

        $validatedData['rep_via_email'] = isset($validatedData['rep_via_email']) ? 1 : 0;
        $validatedData['type_buyer'] = isset($validatedData['type_buyer']) ? 1 : 0;
        $validatedData['type_seller'] = isset($validatedData['type_seller']) ? 1 : 0;
        $validatedData['type_mate'] = isset($validatedData['type_mate']) ? 1 : 0;
        $validatedData['type_agent'] = isset($validatedData['type_agent']) ? 1 : 0;
        $validatedData['marked'] = isset($validatedData['marked']) ? 1 : 0;

        Models\Customer::create($validatedData);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit(Models\Customer $customer)
    {
        $group = Models\CustomerGroup::select('id', 'name')->get();
        $fieldset = $this->fieldset($group);
        return view('customers.edit', compact('customer', 'fieldset'));
    }

    public function update(Request $request, Models\Customer $customer)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|exists:customers,code',
            'name' => 'required|max:20',
            'phone' => 'required',
            'cell' => 'required',
            'fax' => 'required',
            'address' => 'required',
            'postal_code' => 'required',
            'email' => 'required',
            'ecnmcs_code' => 'required',
            'personal_code' => 'required',
            'web_page' => 'required',
            'responsible' => 'required',
            'connector' => 'required',
            'group_id' => 'required',
            'desc' => 'required',
            'rep_via_email' => 'nullable',
            'acc_name_1' => 'required',
            'acc_no_1' => 'required',
            'acc_bank_1' => 'required',
            'acc_name_2' => 'required',
            'acc_no_2' => 'required',
            'acc_bank_2' => 'required',
            'type_buyer' => 'nullable',
            'type_seller' => 'nullable',
            'type_mate' => 'nullable',
            'type_agent' => 'nullable',
            'commission' => 'required',
            'marked' => 'nullable',
            'reason' => 'required',
            'disc_rate' => 'required',
            'balance'=>'required',
            'credit'=>'required',
        ]);

        $validatedData['rep_via_email'] = isset($validatedData['rep_via_email']) ? 1 : 0;
        $validatedData['type_buyer'] = isset($validatedData['type_buyer']) ? 1 : 0;
        $validatedData['type_seller'] = isset($validatedData['type_seller']) ? 1 : 0;
        $validatedData['type_mate'] = isset($validatedData['type_mate']) ? 1 : 0;
        $validatedData['type_agent'] = isset($validatedData['type_agent']) ? 1 : 0;
        $validatedData['marked'] = isset($validatedData['marked']) ? 1 : 0;

        $customer->update($validatedData);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Models\Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function fieldset($customerGroup): array
    {
        return [
            'infoPersonal' => [
                'title' => 'اطلاعات شخصی',
                'data' => [
                    'اطلاعات اصلی' => [
                        'code' => ['label' => 'کد طرف حساب', 'type' => 'text'],
                        'name' => ['label' => 'نام', 'type' => 'text'],
                        'group_id' => ['label' => 'گروه طرف حساب', 'type' => 'select', 'options' => $customerGroup],
                        'ecnmcs_code' => ['label' => 'کد اقتصادی', 'type' => 'text'],
                        'personal_code' => ['label' => 'کد ملی', 'type' => 'text'],
                    ],
                    'اطلاعات طرف حساب' => [
                        'phone' => ['label' => 'تلفن', 'type' => 'number'],
                        'fax' => ['label' => 'فاکس', 'type' => 'number'],
                        'cell' => ['label' => 'موبایل', 'type' => 'number'],
                        'address' => ['label' => 'نشانی', 'type' => 'textarea'],
                        'postal_code' => ['label' => 'کد پستی', 'type' => 'number'],
                        'email' => ['label' => 'ایمیل', 'type' => 'email'],
                        'web_page' => ['label' => 'وب سایت', 'type' => 'text'],
                        'rep_via_email' => ['label' => 'اطلاع رسانی از طریق ایمیل', 'type' => 'checkbox'],
                    ],
                    'اطلاعات تماس' => [
                        'connector' => ['label' => 'رابط', 'type' => 'text'],
                        'responsible' => ['label' => 'responsible', 'type' => 'text'],
                    ],
                    'توضیحات' => [
                        'desc' => ['label' => 'توضیحات', 'type' => 'textarea'],
                        'balance' => ['label' => 'balance', 'type' => 'number'],
                        'credit' => ['label' => 'credit', 'type' => 'number'],
                        'type_buyer' => ['label' => 'type_buyer', 'type' => 'checkbox'],
                        'type_seller' => ['label' => 'type_seller', 'type' => 'checkbox'],
                        'type_mate' => ['label' => 'type_mate', 'type' => 'checkbox'],
                        'type_agent' => ['label' => 'type_agent', 'type' => 'checkbox'],
                        'commission' => ['label' => 'commission', 'type' => 'text'],
                        'marked' => ['label' => 'marked', 'type' => 'checkbox'],
                        'reason' => ['label' => 'reason', 'type' => 'textarea'],
                        'disc_rate' => ['label' => 'disc_rate', 'type' => 'text'],
                    ]
                ]
            ],
            'infoEconomic' => [
                'title' => 'اطلاعات اقتصادی',
                'data' => [
                    'حساب اول' => [
                        'acc_name_1' => ['label' => 'نام', 'type' => 'text'],
                        'acc_no_1' => ['label' => 'شماره حساب', 'type' => 'text'],
                        'acc_bank_1' => ['label' => 'بانک', 'type' => 'text'],
                    ],
                    'حساب دوم' => [
                        'acc_name_2' => ['label' => 'نام', 'type' => 'text'],
                        'acc_no_2' => ['label' => 'شماره حساب', 'type' => 'text'],
                        'acc_bank_2' => ['label' => 'بانک', 'type' => 'text'],
                    ],
                ]
            ]
        ];
//            'subject_id' => ['label' => 'کد', 'type' => 'text'],
//            'balance' => ['label' => 'balance', 'type' => 'textarea'],
//            'credit' => ['label' => 'credit', 'type' => 'textarea'],
//            'type_buyer' => ['label' => 'credit', 'type' => 'textarea'],
//            'type_seller' => ['label' => 'credit', 'type' => 'textarea'],
//            'type_mate' => ['label' => 'credit', 'type' => 'textarea'],
//            'type_agent' => ['label' => 'credit', 'type' => 'textarea'],
//            'commission' => ['label' => 'credit', 'type' => 'textarea'],
//            'marked' => ['label' => 'credit', 'type' => 'textarea'],
//            'reason' => ['label' => 'credit', 'type' => 'textarea'],
//            'disc_rate' => ['label' => 'credit', 'type' => 'text'],
    }
}
