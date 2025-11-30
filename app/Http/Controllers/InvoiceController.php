<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
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
        $builder = Invoice::with(['customer', 'document'])
            ->orderByDesc('id');

        $builder->when($request->filled('invoice_type') &&
            in_array($request->invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']),
            fn ($invoice) => $invoice->where('invoice_type', $request->invoice_type)
        );

        $builder->when($request->filled('number'),
            fn ($q) => $q->where('number', $request->number)
        );

        $builder->when($request->filled('date'),
            fn ($q) => $q->whereDate('date', $request->date)
        );

        $builder->when($request->filled('text'),
            fn ($q) => $q->where(function ($invoice) use ($request) {
                $invoice->whereHas('items', function ($items) use ($request) {
                    $items->where('description', 'like', "%{$request->text}%");
                })->orWhereHas('customer', function ($customer) use ($request) {
                    $customer->where('name', 'like', "%{$request->text}%");
                });
            })
        );

        $invoices = $builder->paginate(12)->appends($request->query());

        return view('invoices.index', compact('invoices'));
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
        $products = Product::with('inventorySubject', 'productGroup')->orderBy('name', 'asc')->get();
        $services = Service::with('subject', 'serviceGroup')->orderBy('name', 'asc')->get();
        $serviceGroups = ServiceGroup::all();
        $productGroups = ProductGroup::all();
        $customers = Customer::with('group')->orderBy('name', 'asc')->get();
        $previousDocumentNumber = floor(Document::max('number') ?? 0);

        $transactions = $this->prepareTransactions();

        $total = count($transactions);

        $invoice_type = in_array($invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']) ? $invoice_type : 'sell';
        $previousInvoiceNumber = floor(Invoice::where('invoice_type', $invoice_type)->max('number') ?? 0);

        return view('invoices.create', compact('products', 'services', 'productGroups', 'serviceGroups', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber', 'invoice_type'));
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

        // Map transactions to invoice items
        $items = collect($validated['transactions'])->map(function ($transaction, $index) {
            return [
                'transaction_index' => $index,
                'itemable_id' => $transaction['item_id'],
                'itemable_type' => $transaction['item_type'],
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
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type])
            ->with('success', __('Invoice created successfully.'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'document',
            'document.transactions',
            'items',
        ]);

        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load('customer', 'items');

        $pdf = PDF::loadView('invoices.print', compact('invoice'));

        return $pdf->stream('invoice-'.($invoice->number ?? $invoice->id).'.pdf');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function edit(Invoice $invoice)
    {
        $invoice->load('customer', 'document.transactions', 'items'); // Eager load relationships

        $customers = Customer::with('group')->orderBy('name', 'asc')->get();
        $products = Product::with(['inventorySubject', 'productGroup'])->orderBy('name', 'asc')->get();
        $services = Service::with(['subject', 'serviceGroup'])->orderBy('name', 'asc')->get();

        $productGroups = ProductGroup::all();
        $serviceGroups = ServiceGroup::all();

        // Prepare transactions from invoice items
        $transactions = $this->prepareTransactions($invoice, 'edit');

        $total = $transactions->count();

        $invoice_type = $invoice->invoice_type;

        return view('invoices.edit', compact(
            'invoice',
            'customers',
            'total',
            'products',
            'services',
            'transactions',
            'productGroups',
            'serviceGroups',
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

        // Map transactions to invoice items
        $items = collect($validated['transactions'])->map(function ($transaction, $index) {
            return [
                'transaction_index' => $index,
                'itemable_id' => $transaction['item_id'],
                'itemable_type' => $transaction['item_type'],
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
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type])
            ->with('success', __('Invoice updated successfully.'));
    }

    public function destroy(Invoice $invoice)
    {
        try {
            InvoiceService::deleteInvoice($invoice->id);

            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('info', __('Invoice deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('error', $e->getMessage());
        }
    }

    private function prepareTransactions($source = null, string $mode = 'create')
    {

        if (old('transactions')) {
            return collect(old('transactions'))->map(function ($transaction, $index) {
                if (! empty($transaction['item_type']) && ! empty($transaction['item_id'])) {

                    if ($transaction['item_type'] === Product::class) {
                        $model = Product::find($transaction['item_id']);
                        $transaction['subject'] = $model?->name;
                        $transaction['product_id'] = $model?->id;
                    }

                    if ($transaction['item_type'] === Service::class) {
                        $model = Service::find($transaction['item_id']);
                        $transaction['subject'] = $model?->name;
                        $transaction['service_id'] = $model?->id;
                        $transaction['quantity'] = $transaction['quantity'] ?? 1;
                    }
                }

                $transaction['id'] = $index + 1;

                return $transaction;
            });
        }

        if ($mode === 'edit' && $source instanceof Invoice) {
            return $source->items->map(function ($item, $index) {
                $subtotalBeforeVat = $item->amount - $item->vat;
                $vatPercentage = $subtotalBeforeVat > 0
                    ? ($item->vat / $subtotalBeforeVat) * 100
                    : 0;

                return [
                    'id' => $index + 1,
                    'transaction_id' => $item->transaction_id,
                    'desc' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit_price,
                    'off' => $item->unit_discount,
                    'vat' => $vatPercentage,
                    'total' => $item->amount,
                    'inventory_subject_id' => $item->itemable->inventory_subject_id
                        ?? $item->itemable->subject_id
                        ?? null,
                    'subject' => $item->itemable->name ?? null,
                    'product_id' => $item->itemable->inventory_subject_id ? $item->itemable->id : null,
                    'service_id' => $item->itemable->subject_id ? $item->itemable->id : null,
                ];
            });
        }

        return collect([[
            'id' => 1,
            'transaction_id' => null,
            'inventory_subject_id' => null,
            'subject' => null,
            'desc' => null,
            'quantity' => 1,
            'unit' => null,
            'off' => null,
            'vat' => null,
            'total' => null,
            'product_id' => null,
            'service_id' => null,
        ]]);
    }

    public function searchCustomer(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $results = [];

        $groupMatches = CustomerGroup::where('name', 'like', "%{$q}%")->pluck('id');

        if ($groupMatches->isNotEmpty()) {
            $customers = Customer::with('group')->whereIn('group_id', $groupMatches)->limit(30)->get(['id', 'name', 'group_id']);
        } else {
            $customers = Customer::with('group')->where('name', 'like', "%{$q}%")->limit(30)->get(['id', 'name', 'group_id']);
        }

        if ($customers->isNotEmpty()) {
            $results[] = [
                'id' => 'group_customers',
                'headerGroup' => 'customer',
                'options' => $this->groupItems($customers, 'group'),
            ];
        }

        return response()->json($results);
    }

    public function searchProductService(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $results = [];

        $productGroupMatches = ProductGroup::where('name', 'like', "%{$q}%")->pluck('id');

        if ($productGroupMatches->isNotEmpty()) {
            $products = Product::with('productGroup')->whereIn('group', $productGroupMatches)->limit(30)->get();
        } else {
            $products = Product::with('productGroup')->where('name', 'like', "%{$q}%")->limit(30)->get();
        }

        if ($products->isNotEmpty()) {
            $results[] = [
                'id' => 'group_products',
                'headerGroup' => 'product',
                'options' => $this->groupItems($products, 'productGroup'),
            ];
        }

        $serviceGroupMatches = ServiceGroup::where('name', 'like', "%{$q}%")->pluck('id');

        if ($serviceGroupMatches->isNotEmpty()) {
            $services = Service::with('serviceGroup')->whereIn('group', $serviceGroupMatches)->limit(30)->get();
        } else {
            $services = Service::with('serviceGroup')->where('name', 'like', "%{$q}%")->limit(30)->get();
        }

        if ($services->isNotEmpty()) {
            $results[] = [
                'id' => 'group_services',
                'headerGroup' => 'service',
                'options' => $this->groupItems($services, 'serviceGroup'),
            ];
        }

        return response()->json($results);
    }

    private function groupItems($items, $relationName)
    {
        $grouped = [];

        foreach ($items as $item) {
            // Get group or default
            $group = $item->$relationName ?? (object) ['id' => 0, 'name' => 'General'];
            $groupId = $group->id;

            if (! isset($grouped[$groupId])) {
                $grouped[$groupId] = [];
            }

            $grouped[$groupId][] = [
                'id' => $item->id,
                'groupId' => $groupId,
                'groupName' => $group->name,
                'text' => $item->name,
                'type' => $relationName === 'productGroup' ? 'product' : 'service',
                'raw_data' => $item->toArray(),
            ];
        }

        // Return as object so JSON encodes it as a Map/Object
        return (object) $grouped;
    }
}
