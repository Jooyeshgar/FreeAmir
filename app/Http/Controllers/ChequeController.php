<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChequeRequest;
use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\ChequeService;

class ChequeController extends Controller
{
    public function __construct(private readonly ChequeService $service) {}

    public function index()
    {
        $cheques = $this->service->paginate();

        return view('cheques.index', compact('cheques'));
    }

    public function create()
    {
        $customers = Customer::query()->pluck('name', 'id');
        $chequeBooks = ChequeBook::query()->pluck('title', 'id');
        $transactions = Transaction::query()->pluck('id', 'id');

        return view('cheques.create', compact('customers', 'chequeBooks', 'transactions'));
    }

    public function store(ChequeRequest $request)
    {
        $this->service->create($request->validated());

        return redirect()->route('cheques.index')->with('success', __('Cheque created successfully.'));
    }

    public function show(Cheque $cheque)
    {
        $cheque->load(['customer', 'chequeBook', 'transaction', 'histories.creator']);

        return view('cheques.show', compact('cheque'));
    }

    public function edit(Cheque $cheque)
    {
        $customers = Customer::query()->pluck('name', 'id');
        $chequeBooks = ChequeBook::query()->pluck('title', 'id');
        $transactions = Transaction::query()->pluck('id', 'id');

        return view('cheques.edit', compact('cheque', 'customers', 'chequeBooks', 'transactions'));
    }

    public function update(ChequeRequest $request, Cheque $cheque)
    {
        $this->service->update($cheque, $request->validated());

        return redirect()->route('cheques.index')->with('success', __('Cheque updated successfully.'));
    }

    public function destroy(Cheque $cheque)
    {
        $this->service->delete($cheque);

        return redirect()->route('cheques.index')->with('success', __('Cheque deleted successfully.'));
    }
}
