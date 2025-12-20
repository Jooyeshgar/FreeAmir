<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\AncillaryCost;
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
    public function __construct()
    {
        $this->middleware('permission:invoices.view', ['only' => ['index']]);
        $this->middleware('permission:invoices.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:invoices.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:invoices.delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function index(Request $request)
    {
        $builder = Invoice::with(['customer', 'document'])
            ->orderByDesc('date')
            ->orderByDesc('number');

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

        $invoices = $builder->paginate(25)->appends($request->query());

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
        $customers = Customer::with('group')->orderBy('name', 'asc')->get();
        $previousDocumentNumber = floor(Document::max('number') ?? 0);

        $transactions = $this->prepareTransactions();

        $total = count($transactions);

        $invoice_type = in_array($invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']) ? $invoice_type : 'sell';
        $previousInvoiceNumber = floor(Invoice::where('invoice_type', $invoice_type)->max('number') ?? 0);

        return view('invoices.create', compact('products', 'services', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber', 'invoice_type'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreInvoiceRequest $request, InvoiceService $service)
    {
        $validated = $request->validated();
        $invoiceData = $this->extractInvoiceData($validated);
        $items = $this->mapTransactionsToItems($validated['transactions']);

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('invoices.approve');
        }

        $result = $service->createInvoice(auth()->user(), $invoiceData, $items, $approved);

        [$msgType, $msg] = $this->invoiceMessage($result, 'created', $approved);

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type])
            ->with($msgType, $msg);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'document',
            'document.transactions',
            'items',
            'ancillaryCosts',
            'ancillaryCosts.customer',
            'ancillaryCosts.document',
            'ancillaryCosts.items',
        ]);

        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load('customer', 'items');

        if (! $invoice->status->isApproved()) {
            return view('invoices.draft', compact('invoice'));
        }

        $pdf = PDF::loadView('invoices.print', compact('invoice'));

        return $pdf->stream('invoice-'.(formatDocumentNumber($invoice->number ?? $invoice->id)).'.pdf');
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
        $previousDocumentNumber = floor(Document::max('number') ?? 0);

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
            'invoice_type',
            'previousDocumentNumber'
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
        $invoiceData = $this->extractInvoiceData($validated);
        $items = $this->mapTransactionsToItems($validated['transactions']);

        InvoiceService::getEditDeleteStatus($invoice);

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('invoices.approve');
        }

        $result = $service->updateInvoice($invoice->id, $invoiceData, $items, $approved);

        [$msgType, $msg] = $this->invoiceMessage($result, 'updated', $approved);

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type])
            ->with($msgType, $msg);
    }

    public function destroy(Invoice $invoice)
    {
        try {
            InvoiceService::getEditDeleteStatus($invoice);

            InvoiceService::deleteInvoice($invoice->id);

            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('info', __('Invoice deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('error', $e->getMessage());
        }
    }

    private function invoiceMessage(array $result, string $action = 'created', bool $approved = false)
    {
        if (! $approved) {
            return [
                'success',
                __("Invoice {$action} successfully."),
            ];
        }

        $documentMissing = empty($result['document']);

        return [
            $documentMissing ? 'warning' : 'success',
            __("Invoice {$action} successfully.")
                .($documentMissing
                    ? ' '.__('but it could not be approved due to validation constraints.')
                    : ''
                ),
        ];
    }

    private function extractInvoiceData(array $validated): array
    {
        return [
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
    }

    private function mapTransactionsToItems(array $transactions): array
    {
        return collect($transactions)->map(fn ($t, $i) => [
            'transaction_index' => $i,
            'itemable_id' => $t['item_id'],
            'itemable_type' => $t['item_type'],
            'quantity' => $t['quantity'] ?? 1,
            'description' => $t['desc'] ?? null,
            'unit_discount' => $t['unit_discount'] ?? 0,
            'vat' => $t['vat'] ?? 0,
            'unit' => $t['unit'] ?? 0,
            'total' => $t['total'] ?? 0,
        ])->toArray();
    }

    private function prepareTransactions($source = null, string $mode = 'create')
    {
        if (old('transactions')) {
            return $this->prepareFromOldInput();
        }

        if ($mode === 'edit' && $source instanceof Invoice) {
            return $this->prepareFromInvoice($source);
        }

        return $this->getEmptyTransaction();
    }

    private function prepareFromOldInput()
    {
        return collect(old('transactions'))->map(function ($transaction, $index) {
            $transaction['id'] = $index + 1;

            if (empty($transaction['item_type']) || empty($transaction['item_id'])) {
                return $transaction;
            }

            $isProduct = $transaction['item_type'] === Product::class;
            $model = $isProduct
                ? Product::find($transaction['item_id'])
                : Service::find($transaction['item_id']);

            $transaction['subject'] = $model?->name;
            $transaction[$isProduct ? 'product_id' : 'service_id'] = $model?->id;
            $transaction['quantity'] ??= 1;

            return $transaction;
        });
    }

    private function prepareFromInvoice(Invoice $invoice)
    {
        return $invoice->items->map(function ($item, $index) {
            $subtotalBeforeVat = $item->amount - $item->vat;
            $isProduct = isset($item->itemable->inventory_subject_id);

            return [
                'id' => $index + 1,
                'transaction_id' => $item->transaction_id,
                'desc' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'off' => $item->unit_discount,
                'vat' => $subtotalBeforeVat > 0 ? ($item->vat / $subtotalBeforeVat) * 100 : 0,
                'total' => $item->amount,
                'inventory_subject_id' => $item->itemable->inventory_subject_id ?? $item->itemable->subject_id ?? null,
                'subject' => $item->itemable->name ?? null,
                'product_id' => $isProduct ? $item->itemable->id : null,
                'service_id' => $isProduct ? null : $item->itemable->id,
            ];
        });
    }

    private function getEmptyTransaction()
    {
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

        $returnableFields = ['id', 'name', 'group_id'];

        $groupMatches = CustomerGroup::where('name', 'like', "%{$q}%")->pluck('id');

        $searchedInCustomersGroups = collect();
        if ($groupMatches->isNotEmpty()) {
            $searchedInCustomersGroups = Customer::with('group')->whereIn('group_id', $groupMatches)->limit(30)->get($returnableFields);
        }

        $searchedInCustomers = Customer::with('group')->where('name', 'like', "%{$q}%")->limit(30)->get($returnableFields);

        $customers = $searchedInCustomers->merge($searchedInCustomersGroups)->unique('id');

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

        $searches = [
            ['group' => ProductGroup::class, 'model' => Product::class, 'relation' => 'productGroup', 'id' => 'group_products', 'header' => 'product'],
            ['group' => ServiceGroup::class, 'model' => Service::class, 'relation' => 'serviceGroup', 'id' => 'group_services', 'header' => 'service'],
        ];

        foreach ($searches as $search) {
            $items = $this->searchWithGroup($q, $search['group'], $search['model'], $search['relation']);

            if ($items->isNotEmpty()) {
                $results[] = [
                    'id' => $search['id'],
                    'headerGroup' => $search['header'],
                    'options' => $this->groupItems($items, $search['relation']),
                ];
            }
        }

        return response()->json($results);
    }

    private function searchWithGroup(string $q, string $groupModel, string $itemModel, string $relation)
    {
        $fields = ['id', 'name', 'group'];

        $groupMatches = $groupModel::where('name', 'like', "%{$q}%")->pluck('id');

        $fromGroups = $groupMatches->isNotEmpty()
            ? $itemModel::with($relation)->whereIn('group', $groupMatches)->limit(30)->get($fields)
            : collect();

        $fromName = $itemModel::with($relation)->where('name', 'like', "%{$q}%")->limit(30)->get($fields);

        return $fromName->merge($fromGroups)->unique('id');
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

    public function changeStatus(Invoice $invoice, string $status, InvoiceService $service)
    {
        if (! in_array($status, ['approved', 'unapproved'])) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])
                ->with('error', __('Invalid status action.'));
        }

        $decision = $service->getChangeStatusDecision($invoice, $status);
        if ($decision->hasErrors()) {
            $error = $decision->messages->first(fn ($m) => $m->type === 'error');

            return redirect()->back()->with('error', $error?->text ?? __('Invalid invoice status.'));
        }

        if ($decision->needsConfirmation && ! request()->has('confirm')) {
            $warning = $decision->messages->first(fn ($m) => $m->type === 'warning');

            return redirect()->back()->with('warning', $warning?->text ?? __('Please confirm your action.'))->with('confirm_invoice_status_change', true)->with('conflicting_invoices', $decision->conflicts->map(fn ($i) => ['id' => $i->id, 'number' => $i->number, 'type' => $i->invoice_type])->values()->all());
        }

        $service->changeInvoiceStatus($invoice, $status);

        $message = $status === 'approved' ? __('Invoice approved successfully.') : __('Invoice unapproved successfully.');

        return redirect()->back()->with('success', __($message));
    }

    public function groupAction(Invoice $invoice, $conflicts = null)
    {
        $conflicts ??= json_decode(request()->query('conflicts'), true);
        $formattedConflicts = self::formattedConflicts($conflicts);

        $allConflicts = $this->findAllConflictsRecursively($formattedConflicts);

        $fullFormattedConflicts = self::paginateConflicts($allConflicts, 10, request()->get('page', 1));

        return view('invoices.group-action', compact('invoice', 'fullFormattedConflicts'));
    }

    /**
     * Recursively find all conflicts for invoices and ancillary costs
     */
    private function findAllConflictsRecursively(array $initialConflicts): array
    {
        $allConflicts = [];
        $processedIds = []; // Track processed items to avoid infinite loops

        foreach ($initialConflicts as $conflict) {
            if (! isset($conflict['recursive_type'])) {
                $allConflicts[] = $conflict;

                continue;
            }

            $model = $conflict['recursive_type'];
            $modelClass = get_class($model);
            $modelId = $model->id;
            $key = $modelClass.':'.$modelId;

            // Skip if already processed
            if (in_array($key, $processedIds)) {
                continue;
            }

            $processedIds[] = $key;
            $allConflicts[] = $conflict;

            // Find conflicts based on model type
            if ($model instanceof Invoice) {
                $nestedConflicts = $this->findInvoiceConflictsRecursively($model, $processedIds);
                $allConflicts = array_merge($allConflicts, $nestedConflicts);
            } elseif ($model instanceof AncillaryCost) {
                $nestedConflicts = $this->findAncillaryCostConflictsRecursively($model, $processedIds);
                $allConflicts = array_merge($allConflicts, $nestedConflicts);
            }
        }

        return $allConflicts;
    }

    /**
     * Find all conflicts for an invoice recursively
     *
     * @param  Invoice  $invoice  The invoice to check
     * @param  array  $processedIds  Already processed items to avoid loops
     * @return array Formatted conflicts
     */
    private function findInvoiceConflictsRecursively(Invoice $invoice, array &$processedIds): array
    {
        if (! isset($invoice->status)) { // must have better code
            $invoice = Invoice::findOrFail($invoice->id);
        }
        $decision = InvoiceService::getChangeStatusValidation($invoice);

        $formattedConflicts = [];

        // Process all conflicts from the decision
        foreach ($decision->conflicts as $conflict) {
            $modelClass = get_class($conflict);
            $key = $modelClass.':'.$conflict->id;

            // Skip if already processed
            if (in_array($key, $processedIds)) {
                continue;
            }

            $processedIds[] = $key;

            // Format the conflict
            $formatted = $this->formatSingleConflict($conflict);
            $formattedConflicts[] = $formatted;

            // Recursively find nested conflicts
            if ($conflict instanceof Invoice) {
                $nestedConflicts = $this->findInvoiceConflictsRecursively($conflict, $processedIds);
                $formattedConflicts = array_merge($formattedConflicts, $nestedConflicts);
            } elseif ($conflict instanceof AncillaryCost) {
                $nestedConflicts = $this->findAncillaryCostConflictsRecursively($conflict, $processedIds);
                $formattedConflicts = array_merge($formattedConflicts, $nestedConflicts);
            }
        }

        return $formattedConflicts;
    }

    /**
     * Find all conflicts for an ancillary cost recursively
     *
     * @param  AncillaryCost  $ancillaryCost  The ancillary cost to check
     * @param  array  $processedIds  Already processed items to avoid loops
     * @return array Formatted conflicts
     */
    private function findAncillaryCostConflictsRecursively(AncillaryCost $ancillaryCost, array &$processedIds): array
    {
        $formattedConflicts = [];

        // Get validation for ancillary cost - note: returns array, not Decision object
        $validation = \App\Services\AncillaryCostService::getChangeStatusValidation($ancillaryCost);

        // If not allowed, the ancillary cost itself has blocking issues
        // We need to find what's blocking it by checking its related invoice
        if (! $validation['allowed'] && $ancillaryCost->invoice) {
            $invoice = $ancillaryCost->invoice;
            $key = Invoice::class.':'.$invoice->id;

            if (! in_array($key, $processedIds)) {
                $processedIds[] = $key;

                $formatted = $this->formatSingleConflict($invoice);
                $formattedConflicts[] = $formatted;

                // Recursively find conflicts for the invoice
                $nestedConflicts = $this->findInvoiceConflictsRecursively($invoice, $processedIds);
                $formattedConflicts = array_merge($formattedConflicts, $nestedConflicts);
            }
        }

        return $formattedConflicts;
    }

    /**
     * Format a single conflict model into display array
     *
     * @param  mixed  $conflict  Invoice, AncillaryCost, or Product model
     * @return array Formatted conflict data
     */
    private function formatSingleConflict($conflict): array
    {
        $formatted = [];
        $formatted['recursive_type'] = $conflict;

        if ($conflict instanceof Invoice) {
            $invoice = Invoice::findOrFail($conflict['id']);
            $formatted['recursive_type'] = $invoice;
            $formatted['type'] = __('Invoice').' '.$invoice->invoice_type->label();
            $formatted['customer']['name'] = $invoice->customer->name ?? '';
            $formatted['customer']['id'] = $invoice->customer->id ?? '';
            $formatted['price'] = isset($invoice->amount, $invoice->subtraction)
                ? formatNumber($invoice->amount - $invoice->subtraction)
                : '';
            $formatted['status'] = $invoice->status->label() ?? '';
        } elseif ($conflict instanceof AncillaryCost) {
            $ancillaryCost = AncillaryCost::findOrFail($conflict['id']);
            $formatted['type'] = __('Ancillary Cost');
            $formatted['customer']['name'] = $ancillaryCost->customer->name ?? '';
            $formatted['customer']['id'] = $ancillaryCost->customer->id ?? '';
            $formatted['price'] = $ancillaryCost->amount ? formatNumber((float) $ancillaryCost->amount) : '';
            $formatted['status'] = $ancillaryCost->status->label() ?? '';
        } elseif ($conflict instanceof Product) {
            $formatted['type'] = __('Product');
            $formatted['customer'] = '-';
            $formatted['price'] = isset($conflict->average_cost) ? formatNumber($conflict->average_cost) : '';
            $formatted['status'] = '-';
        }

        return $formatted;
    }

    private static function paginateConflicts($conflicts, $perPage, $page)
    {
        $conflictsCollection = collect($conflicts);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $conflictsCollection->forPage($page, $perPage),
            $conflictsCollection->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function formattedConflicts(array $conflicts): array
    {
        return array_map(function ($conflict) {
            $formatted = [];

            if (in_array($conflict['type'], ['buy', 'sell'])) {
                $invoice = Invoice::findOrFail($conflict['id']);
                $formatted['recursive_type'] = $invoice;
                $formatted['type'] = __('Invoice').' '.$invoice->invoice_type->label() ?? '';
                $formatted['customer']['name'] = $invoice->customer->name ?? '';
                $formatted['customer']['id'] = $invoice->customer->id ?? '';
                $formatted['price'] = isset($invoice->amount, $invoice->subtraction) ? formatNumber($invoice->amount - $invoice->subtraction) : '';
                $formatted['status'] = $invoice->status->label() ?? '';
            } elseif ($conflict['type'] === 'product') {
                $product = Product::findOrFail($conflict['id']);
                $formatted['type'] = 'product';
                $formatted['customer'] = '-';
                $formatted['price'] = isset($product->average_cost) ? formatNumber($product->average_cost) : '';
                $formatted['status'] = '-';
            } elseif ($conflict['type'] === 'ancillary_cost') {
                $ancillaryCost = AncillaryCost::findOrFail($conflict['id']);
                $formatted['recursive_type'] = $ancillaryCost;
                $formatted['type'] = 'ancillary cost';
                $formatted['customer'] = $ancillaryCost->customer->name ?? '';
                $formatted['price'] = $ancillaryCost->amount ? formatNumber((float) $ancillaryCost->amount) : '';
                $formatted['status'] = $ancillaryCost->status->label() ?? '';
            }

            return $formatted;
        }, $conflicts);
    }
}
