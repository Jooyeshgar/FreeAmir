<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use PDF;

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
        if (empty(config('amir.inventory'))) {
            return redirect()->route('configs.index')->with('error', __('Inventory Subject is not configured. Please set it in configurations.'));
        }

        if (empty(config('amir.cust_subject'))) {
            return redirect()->route('configs.index')->with('error', __('Customer Subject is not configured. Please set it in configurations.'));
        }
        $products = Product::with('subject')->orderBy('name', 'asc')->get();
        $productGroups = ProductGroup::all();
        $customers = Customer::all('name', 'id');
        $previousInvoiceNumber = Invoice::orderBy('id', 'desc')->first()->number ?? 0;
        $previousDocumentNumber = Document::orderBy('id', 'desc')->first()->number ?? 0;
        $transactions = old('transactions') ?? $this->preparedTransactions(collect([new Transaction]));

        $total = count($transactions);

        // Validate and convert invoice_type to enum value
        $invoice_type = in_array($invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']) ? $invoice_type : 'sell';

        return view('invoices.create', compact('products', 'productGroups', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber', 'invoice_type'));
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
                'unit' => $transaction['unit'] ?? 0,
                'total' => $transaction['total'] ?? 0,
            ];
        })->toArray();

        $result = $service->createInvoice(auth()->user(), $invoiceData, $items);

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type->value])
            ->with('success', __('Invoice created successfully.'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('customer', 'items.product');

        $pdf = PDF::loadView('invoices.show', compact('invoice'));

        return $pdf->stream('invoice-'.($invoice->number ?? $invoice->id).'.pdf');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function edit(Invoice $invoice)
    {
        $invoice->load('customer', 'document.transactions', 'items.product'); // Eager load relationships

        $customers = Customer::all();
        $products = Product::with('subject')->orderBy('name', 'asc')->get();
        $productGroups = [];

        // Prepare transactions from invoice items
        $transactions = $invoice->items->map(function ($item, $index) {
            // Calculate VAT percentage: (vat_amount / subtotal_before_vat) * 100
            $subtotalBeforeVat = $item->amount - $item->vat;
            $vatPercentage = $subtotalBeforeVat > 0 ? ($item->vat / $subtotalBeforeVat) * 100 : 0;

            return [
                'id' => $index + 1,
                'transaction_id' => $item->transaction_id,
                'product_id' => $item->product_id,
                'subject_id' => $item->product->subject_id,
                'subject' => $item->product->name,
                'desc' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'off' => $item->unit_discount,
                'vat' => $vatPercentage,
                'total' => $item->amount,
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
            'invoice_type',
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
                'unit' => $transaction['unit'] ?? 0,
                'total' => $transaction['total'] ?? 0,
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
            InvoiceService::deleteInvoice($invoice->id);

            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type->value])->with('success', __('Invoice deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type->value])->with('error', $e->getMessage());
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
