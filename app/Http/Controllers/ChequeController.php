<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChequeRequest;
use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ChequeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cheques.index', ['only' => ['index', 'show']]);
        $this->middleware('permission:cheques.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:cheques.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:cheques.delete', ['only' => ['destroy']]);
    }

    public function index(ChequeBook $chequeBook)
    {
        $query = Cheque::query()->where('cheque_book_id', $chequeBook->id);

        if (request()->has('due_date') && request('due_date')) {
            $query->where('due_date', convertToGregorian(request('due_date')));
        }

        if (request()->has('customer_name') && request('customer_name')) {
            $customerSearchName = request('customer_name');
            $query->where(function ($q) use ($customerSearchName) {
                $q->whereHas('customer', function ($subQ) use ($customerSearchName) {
                    $subQ->where('name', 'like', '%'.$customerSearchName.'%');
                });
            });
        }

        if (request()->has('serial_number') && request('serial_number')) {
            $query->where('serial', 'like', '%'.request('serial_number').'%');
        }

        if (request()->has('status') && request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }

        $cheques = $query->paginate(10);

        return view('cheques.index', compact('cheques', 'chequeBook'));
    }

    public function list(Request $request)
    {
        $query = Cheque::query();

        if (request()->has('due_date') && request('due_date')) {
            $query->where('due_date', convertToGregorian(request('due_date')));
        }

        if (request()->has('customer_name') && request('customer_name')) {
            $customerSearchName = request('customer_name');
            $query->where(function ($q) use ($customerSearchName) {
                $q->whereHas('customer', function ($subQ) use ($customerSearchName) {
                    $subQ->where('name', 'like', '%'.$customerSearchName.'%');
                });
            });
        }

        if (request()->has('cheque_book_title') && request('cheque_book_title')) {
            $chequeBookSearchTitle = request('cheque_book_title');
            $query->where(function ($q) use ($chequeBookSearchTitle) {
                $q->whereHas('chequeBook', function ($subQ) use ($chequeBookSearchTitle) {
                    $subQ->where('title', 'like', '%'.$chequeBookSearchTitle.'%');
                });
            });
        }

        if (request()->has('serial_number') && request('serial_number')) {
            $query->where('serial', request('serial_number'));
        }

        if (request()->has('status') && request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }

        $cheques = $query->paginate(10);

        return view('cheques.list', compact('cheques'));

    }

    public function create(ChequeBook $chequeBook)
    {
        $customers = Customer::query()->pluck('name', 'id');
        $transactions = Transaction::query()->pluck('desc', 'id');
        $transactions = $transactions->map(fn ($transaction) => [
            'id' => $transaction->id,
            'groupId' => 0,
            'groupName' => 'General',
            'text' => $transaction->desc.' - '.$transaction->subject->name.' - '.formatCode($transaction->subject->code),
            'type' => 'transaction',
        ])->all();

        return view('cheques.create', compact('customers', 'chequeBook', 'transactions'));
    }

    public function store(ChequeRequest $request, ChequeBook $chequeBook)
    {
        $data = $request->validated();
        $data['cheque_book_id'] = $chequeBook->id;
        Cheque::create($data);

        return redirect()->route('cheques.index', $chequeBook)->with('success', __('Cheque created successfully.'));
    }

    public function show(ChequeBook $chequeBook, Cheque $cheque)
    {
        return view('cheques.show', compact('cheque'));
    }

    public function edit(Cheque $cheque)
    {
        $customerIdsForSelect = Customer::orderBy('name')->limit(20)->pluck('id');

        $transactionIdsForSelect = Transaction::limit(20)->pluck('id');

        $customers = Customer::with('group')->whereIn('id', $customerIdsForSelect->push($cheque->customer_id)->unique())
            ->orderBy('name')->get();

        $transactions = Transaction::whereIn('id', $transactionIdsForSelect->push($cheque->transaction_id)->unique())->get();
        $transactions = $transactions->map(fn ($transaction) => [
            'id' => $transaction->id,
            'groupId' => 0,
            'groupName' => 'General',
            'text' => $transaction->desc.' - '.$transaction->subject->name.' - '.formatCode($transaction->subject->code),
            'type' => 'transaction',
        ])->all();

        return view('cheques.edit', compact('cheque', 'customers', 'transactions'));
    }

    public function update(ChequeRequest $request, Cheque $cheque)
    {
        $cheque->update($request->validated());

        return redirect()->route('cheques.index', $cheque->chequeBook)->with('success', __('Cheque updated successfully.'));
    }

    public function destroy(Cheque $cheque)
    {
        $cheque->delete();

        return redirect()->back()->with('success', __('Cheque deleted successfully.'));
    }

    public function searchCustomer(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $results = [];

        $returnableFields = ['id', 'name', 'group_id'];

        $groupMatches = CustomerGroup::where('name', 'like', "%{$q}%")->pluck('id');

        $searchedInCustomersGroups = collect();
        if ($groupMatches->isNotEmpty()) {
            $searchedInCustomersGroups = Customer::with('group')->whereIn('group_id', $groupMatches)->limit(30)->get($returnableFields);
        }

        $searchedInCustomers = Customer::with('group')->where('name', 'like', "%{$q}%")->limit(30)->get($returnableFields);

        $customers = $searchedInCustomers->merge($searchedInCustomersGroups)->unique('id');

        $options = (object) [
            0 => $customers->map(fn ($customer) => [
                'id' => $customer->id,
                'groupId' => 0,
                'groupName' => $customer->group->name,
                'text' => $customer->name,
                'type' => 'customer',
            ])->all(),
        ];

        if ($customers->isNotEmpty()) {
            $results[] = [
                'id' => 'group_customers',
                'headerGroup' => 'customer',
                'options' => $options,
            ];
        }

        return response()->json($results);
    }

    public function searchTransaction(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $transactions = Transaction::where('desc', 'like', "%{$q}%")->select('id', 'desc')->limit(20)->get();

        if ($transactions->isEmpty()) {
            return response()->json([]);
        }

        $options = (object) [
            0 => $transactions->map(fn ($transaction) => [
                'id' => $transaction->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $transaction->desc.' - '.$transaction->subject->name.' - '.formatCode($transaction->subject->code),
                'type' => 'transaction',
            ])->all(),
        ];

        return response()->json([
            [
                'id' => 'group_transactions',
                'headerGroup' => 'transaction',
                'options' => $options,
            ],
        ]);
    }
}
