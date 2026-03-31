<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChequeRequest;
use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\ChequeService;
use Illuminate\Http\Request;

class ChequeController extends Controller
{
    public function __construct(private readonly ChequeService $service)
    {
        $this->middleware('permission:cheques.index', ['only' => ['index', 'show']]);
        $this->middleware('permission:cheques.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:cheques.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:cheques.delete', ['only' => ['destroy']]);
    }

    public function index(ChequeBook $chequeBook)
    {
        $cheques = $this->service->someCheques($chequeBook);

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
        $transactions = Transaction::query()->pluck('id', 'id');

        return view('cheques.create', compact('customers', 'chequeBook', 'transactions'));
    }

    public function store(ChequeRequest $request, ChequeBook $chequeBook)
    {
        $data = $request->validated();
        $data['cheque_book_id'] = $chequeBook->id;

        $this->service->create($data);

        return redirect()->route('cheques.index', $chequeBook)->with('success', __('Cheque created successfully.'));
    }

    public function show(ChequeBook $chequeBook, Cheque $cheque)
    {
        return view('cheques.show', compact('cheque'));
    }

    public function edit(Cheque $cheque)
    {
        $customers = Customer::query()->pluck('name', 'id');
        $transactions = Transaction::pluck('id', 'id');

        return view('cheques.edit', compact('cheque', 'customers', 'transactions'));
    }

    public function update(ChequeRequest $request, Cheque $cheque)
    {
        $this->service->update($cheque, $request->validated());

        return redirect()->route('cheques.index')->with('success', __('Cheque updated successfully.'));
    }

    public function destroy(Cheque $cheque)
    {
        $this->service->delete($cheque);

        return redirect()->back()->with('success', __('Cheque deleted successfully.'));
    }
}
