<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChequeBookRequest;
use App\Models\BankAccount;
use App\Models\ChequeBook;
use App\Models\Company;
use App\Services\ChequeBookService;

class ChequeBookController extends Controller
{
    public function __construct(private readonly ChequeBookService $service)
    {
        $this->middleware('permission:cheque-books.index', ['only' => ['index', 'show']]);
        $this->middleware('permission:cheque-books.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:cheque-books.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:cheque-books.delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $chequeBooks = $this->service->paginate();

        return view('cheque-books.index', compact('chequeBooks'));
    }

    public function create()
    {
        $bankAccounts = BankAccount::query()->pluck('name', 'id');

        return view('cheque-books.create', compact('bankAccounts'));
    }

    public function store(ChequeBookRequest $request)
    {
        $this->service->create($request->validated());

        return redirect()->route('cheque-books.index')->with('success', __('Cheque book created successfully.'));
    }

    public function show(ChequeBook $chequeBook)
    {
        $chequeBook->load(['bankAccount', 'cheques']);

        return view('cheque-books.show', compact('chequeBook'));
    }

    public function edit(ChequeBook $chequeBook)
    {
        $companies = Company::query()->pluck('name', 'id');
        $bankAccounts = BankAccount::query()->pluck('name', 'id');

        return view('cheque-books.edit', compact('chequeBook', 'companies', 'bankAccounts'));
    }

    public function update(ChequeBookRequest $request, ChequeBook $chequeBook)
    {
        $this->service->update($chequeBook, $request->validated());

        return redirect()->route('cheque-books.index')->with('success', __('Cheque book updated successfully.'));
    }

    public function destroy(ChequeBook $chequeBook)
    {
        $this->service->delete($chequeBook);

        return redirect()->route('cheque-books.index')->with('success', __('Cheque book deleted successfully.'));
    }
}
