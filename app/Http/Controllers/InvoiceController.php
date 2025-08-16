<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function index(Request $request)
    {
        $builder = Invoice::with('customer', 'document')->orderByDesc('id');

        // Optional: Filter by search query
        $searchTerm = $request->get('q');
        if ($searchTerm) {
            $builder->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            })->orWhereHas('customer', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $invoices = $builder->paginate(12);
        $invoices->appends($request->query());

        return view('invoices.index', compact('invoices', 'searchTerm'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function create()
    {
        $subjects = Subject::where('parent_id', config('amir.product'))->with('children')->orderBy('code', 'asc')->get();
        
        $previousInvoiceNumber = Invoice::orderBy('id', 'desc')->first()->number ?? 0;
        $previousDocumentNumber = Document::orderBy('id', 'desc')->first()->number ?? 0;
        $transactions = old('transactions') ?? $this->preparedTransactions(collect([new Transaction]));

        $total = count($transactions);

        return view('invoices.create', compact('subjects', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|unique:invoices,code|max:255',
            'date' => 'required|date',
            'document_id' => 'required|integer|exists:documents,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'addition' => 'numeric|nullable',
            'subtraction' => 'numeric|nullable',
            'tax' => 'numeric|nullable',
            'ship_date' => 'nullable|date',
            'ship_via' => 'string|nullable|max:255',
            'description' => 'string|nullable|max:255',
            'vat' => 'numeric|nullable',
            'amount' => 'numeric|nullable',
        ]);

        // Normalize checkbox booleans
        $data['cash_payment'] = $request->has('cash_payment') ? 1 : 0;
        $data['permanent'] = $request->has('permanent') ? 1 : 0;
        $data['is_sell'] = $request->has('is_sell') ? 1 : 0;
        $data['active'] = $request->has('active') ? 1 : 0;

        Invoice::create($data);

        return redirect()->route('invoices.index')->with('success', __('Invoice created successfully.'));
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function show(Invoice $invoice)
    {
        $invoice->load('customer', 'document'); // Eager load customer and document data

        return view('invoices.show', compact('invoice'));
    }

    // /**
    //  * Show the form for editing the specified resource.
    //  *
    //  * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    //  */
    // public function edit(Invoice $invoice)
    // {
    //     $invoice->load('customer', 'document'); // Eager load customer and document data

    //     $customers = Customer::pluck('name', 'id');
    //     $documents = Document::pluck('number', 'id');

    //     return view('invoices.edit', compact('invoice', 'customers', 'documents'));
    // }

    // /**
    //  * Update the specified resource in storage.
    //  *
    //  * @return \Illuminate\Http\RedirectResponse
    //  */
    // public function update(Request $request, Invoice $invoice)
    // {
    //     $data = $request->validate([
    //         'code' => 'required|unique:invoices,code,' . $invoice->id . '|max:255',
    //         'date' => 'required|date',
    //         'document_id' => 'required|integer|exists:documents,id',
    //         'customer_id' => 'required|integer|exists:customers,id',
    //         'addition' => 'numeric|nullable',
    //         'subtraction' => 'numeric|nullable',
    //         'tax' => 'numeric|nullable',
    //         'ship_date' => 'nullable|date',
    //         'ship_via' => 'string|nullable|max:255',
    //         'description' => 'string|nullable|max:255',
    //         'vat' => 'numeric|nullable',
    //         'amount' => 'numeric|nullable',
    //     ]);

    //     // Normalize checkbox booleans
    //     $data['cash_payment'] = $request->has('cash_payment') ? 1 : 0;
    //     $data['permanent'] = $request->has('permanent') ? 1 : 0;
    //     $data['is_sell'] = $request->has('is_sell') ? 1 : 0;
    //     $data['active'] = $request->has('active') ? 1 : 0;

    //     $invoice->update($data);

    //     return redirect()->route('invoices.index')->with('success', __('Invoice updated successfully.'));
    // }

    public function destroy(Invoice $invoice)
    {
        try {
            $invoice->delete();
            return redirect()->route('invoices.index')->with('success', __('Invoice deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index')->with('error', $e->getMessage());
        }
    }

    private function preparedTransactions($transactions)
    {
        return $transactions->map(function ($transaction, $i) {
            return [
                'id' => $i + 1,
                'transaction_id' => $transaction->id,
                'subject_id' => $transaction->subject_id,
                'subject' => $transaction->subject?->name,
                'code' => $transaction->subject?->code,
                'desc' => $transaction->desc,
                'credit' => $transaction->credit,
                'debit' => $transaction->debit,
            ];
        });
    }
}
