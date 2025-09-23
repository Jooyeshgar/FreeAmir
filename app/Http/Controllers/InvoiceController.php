<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use App\Http\Requests\StoreInvoiceRequest;

class InvoiceController extends Controller
{
    public function __construct() {}

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
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        if (empty(config('amir.product'))) {
            return redirect()->route('configs.index')->with('error', __('Product Subject is not configured. Please set it in configurations.'));
        }

        if (empty(config('amir.cust_subject'))) {
            return redirect()->route('configs.index')->with('error', __('Customer Subject is not configured. Please set it in configurations.'));
        }
        $products = Product::all();
        $subjects = Subject::where('parent_id', config('amir.product'))->with('children')->orderBy('code', 'asc')->get();
        $customers = Subject::where('parent_id', config('amir.cust_subject'))->with('children')->orderBy('code', 'asc')->get();
        $previousInvoiceNumber = Invoice::orderBy('id', 'desc')->first()->number ?? 1;
        $previousDocumentNumber = Document::orderBy('id', 'desc')->first()->number ?? 1;
        $transactions = old('transactions') ?? $this->preparedTransactions(collect([new Transaction]));

        $total = count($transactions);

        return view('invoices.create', compact('products', 'subjects', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreInvoiceRequest $request, InvoiceService $service)
    {
        // Use validated and normalized data
        $validated = $request->validated();

        $invoiceData = [
            'title' => $validated['title'],
            'date' => $validated['date'],
            'is_sell' => (bool) $validated['invoice_type'],
            'customer_id' => (int) $validated['customer_id'],
            'document_number' => $validated['document_number'],
            'number' => (int) $validated['invoice_number'],
            'cash_payment' => $validated['cash_payment'] ?? 0,
            'addition' => $validated['additions'] ?? 0,
            'subtraction' => $validated['subtractions'] ?? 0,
            'invoice_id' => $validated['invoice_id'] ?? null,
        ];

        // Map transactions for document creation (value computed in service using credit/debit or value)
        $transactions = collect($validated['transactions'])
            ->map(function ($t) {
                // We'll send value via 'value' as total with sign positive
                return [
                    'subject_id' => $t['subject_id'],
                    'desc' => $t['desc'] ?? null,
                    'value' => $t['quantity'] ?? 1,
                ];
            })
            ->toArray();

        // Prepare invoice items mapping by index to link back to created transactions
        // Optimize: prefetch subjects and resolve products via morph (Subject::subjectable)
        $subjectIds = collect($validated['transactions'])
            ->pluck('subject_id')
            ->filter()
            ->unique()
            ->values();
        $subjectsById = Subject::with('subjectable')->whereIn('id', $subjectIds)->get()->keyBy('id');

        $items = collect($validated['transactions'])
            ->values()
            ->map(function ($t, $index) use ($subjectsById) {
                $subject = $subjectsById->get($t['subject_id']);
                $productId = null;
                if ($subject && $subject->subjectable instanceof Product) {
                    $productId = $subject->subjectable->id;
                }
                return [
                    'transaction_index' => $index,
                    'product_id' => $productId,
                    'quantity' => $t['quantity'] ?? 1,
                    'description' => $t['desc'] ?? null,
                ];
            })
            ->toArray();
        // dd($items, $transactions, $invoiceData);
        $result = $service->createInvoice(auth()->user(), $transactions, $invoiceData, $items);

        return (!empty($result))
            ? redirect()->route('invoices.index')->with('success', ('Invoice created successfully.'))
            : back()->with('error', __('Something went wrong creating the invoice.'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    // public function store(Request $request)
    // {

    //     $data = $request->validate([
    //         'code' => 'required|unique:invoices,code|max:255',
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

    //     Invoice::create($data);

    //     return redirect()->route('invoices.index')->with('success', __('Invoice created successfully.'));
    // }

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
