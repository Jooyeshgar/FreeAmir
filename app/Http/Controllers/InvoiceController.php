<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

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

        $invoiceType = $request->get('invoice_type');
        if ($invoiceType && in_array($invoiceType, ['buy', 'sell', 'return_buy', 'return_sell'])) {
            $builder = $builder->where('invoice_type', $invoiceType);
        }

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
    public function create($invoice_type)
    {
        if (empty(config('amir.product'))) {
            return redirect()->route('configs.index')->with('error', __('Product Subject is not configured. Please set it in configurations.'));
        }

        if (empty(config('amir.cust_subject'))) {
            return redirect()->route('configs.index')->with('error', __('Customer Subject is not configured. Please set it in configurations.'));
        }
        $products = Product::with('subject')->orderBy('name', 'asc')->get();
        $productGroups = ProductGroup::all();
        $customers = $this->getCustomers();
        $previousInvoiceNumber = Invoice::orderBy('id', 'desc')->first()->number ?? 1;
        $previousDocumentNumber = Document::orderBy('id', 'desc')->first()->number ?? 1;
        $transactions = old('transactions') ?? $this->preparedTransactions(collect([new Transaction]));

        $total = count($transactions);

        // Validate and convert invoice_type to enum value
        $invoice_type = in_array($invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']) ? $invoice_type : 'sell';

        return view('invoices.create', compact('products', 'productGroups', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber', 'invoice_type'));
    }

    private function getCustomers()
    {
        $full_customers = Subject::where('parent_id', config('amir.cust_subject'))->with('children')->orderBy('code', 'asc')->get();

        foreach ($full_customers as $full_customer) {
            $customers = $full_customer->children;
        }

        return $customers;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreInvoiceRequest $request, InvoiceService $service)
    {
        $validated = $request->validated();

        $invoiceData = [
            'title' => $validated['title'],
            'date' => $validated['date'],
            'invoice_type' => InvoiceType::from($validated['invoice_type']),
            'customer_id' => $validated['customer_id'],
            'document_number' => $validated['document_number'],
            'number' => $validated['invoice_number'],
            'subtraction' => $validated['subtractions'] ?? 0,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ];

        // Fetch all products by subject_id in one query
        $subjectIds = collect($validated['transactions'])->pluck('subject_id')->unique();
        $productsBySubjectId = Product::whereIn('subject_id', $subjectIds)->get()->keyBy('subject_id');

        // Map transactions to invoice items
        $items = collect($validated['transactions'])->map(function ($transaction, $index) use ($productsBySubjectId) {
            $product = $productsBySubjectId->get($transaction['subject_id']);

            return [
                'transaction_index' => $index,
                'product_id' => $product?->id,
                'quantity' => $transaction['quantity'] ?? 1,
                'description' => $transaction['desc'] ?? null,
                'unit_discount' => $transaction['unit_discount'] ?? 0,
                'vat' => $transaction['vat'] ?? 0,
            ];
        })->toArray();

        $result = $service->createInvoice(auth()->user(), $invoiceData, $items);

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type->value])
            ->with('success', __('Invoice created successfully.'));
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
        $invoice->load('customer');
        $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();
        $invoiceItems->load('product');

        return view('invoices.show', compact('invoice', 'invoiceItems'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function edit(Invoice $invoice)
    {
        $invoice->load('customer', 'document.transactions', 'items.product'); // Eager load relationships

        $customers = $this->getCustomers();
        $products = Product::with('subject')->orderBy('name', 'asc')->get();
        $productGroups = ProductGroup::all();

        // Prepare transactions from invoice items
        $transactions = $invoice->items->map(function ($item, $index) {
            $transaction = $item->transaction;
            $product = $item->product;
            dd($item);

            return [
                'id' => $index + 1,
                'transaction_id' => $transaction->id ?? null,
                'product_id' => $product->id ?? null,
                'subject_id' => $product->subject_id ?? null,
                'subject' => $product->name ?? '',
                'code' => $product->subject->code ?? '',
                'desc' => $transaction->desc ?? $item->description ?? '',
                'quantity' => $item->quantity ?? 1,
                'unit' => $transaction ? abs($transaction->value) / ($item->quantity ?: 1) : 0,
                'off' => $item->unit_discount ?? 0,
                'vat' => (abs($item->vat) != 0) ? abs($transaction->value) / abs($item->vat) : 0,
                'total' => $transaction ? abs($transaction->value) : 0,
                'credit' => $transaction->credit ?? 0,
                'debit' => $transaction->debit ?? 0,
            ];
        });
        $total = $transactions->count();
        $invoice_type = $invoice->invoice_type->value;

        return view('invoices.edit', compact(
            'invoice',
            'customers',
            'total',
            'products',
            'transactions',
            'productGroups',
            'invoice_type'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(StoreInvoiceRequest $request, Invoice $invoice, InvoiceService $service)
    {
        $validated = $request->validated();

        $invoiceData = [
            'title' => $validated['title'],
            'date' => $validated['date'],
            'invoice_type' => InvoiceType::from($validated['invoice_type']),
            'customer_id' => $validated['customer_id'],
            'document_number' => $validated['document_number'],
            'number' => $validated['invoice_number'],
            'subtraction' => $validated['subtractions'] ?? 0,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
        // Fetch all products by subject_id in one query
        $subjectIds = collect($validated['transactions'])->pluck('subject_id')->unique();
        $productsBySubjectId = Product::whereIn('subject_id', $subjectIds)->get()->keyBy('subject_id');
        // Map transactions to invoice items
        $items = collect($validated['transactions'])->map(function ($transaction, $index) use ($productsBySubjectId) {
            $product = $productsBySubjectId->get($transaction['subject_id']);

            return [
                'transaction_index' => $index,
                'product_id' => $product?->id,
                'quantity' => $transaction['quantity'] ?? 1,
                'description' => $transaction['desc'] ?? null,
                'unit_discount' => $transaction['unit_discount'] ?? 0,
                'vat' => $transaction['vat'] ?? 0,
            ];
        })->toArray();

        $result = $service->updateInvoice($invoice->id, $invoiceData, $items);

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type->value])
            ->with('success', __('Invoice updated successfully.'));
    }

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
