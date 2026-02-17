<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
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
use App\Services\AncillaryCostService;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use PDF;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly AncillaryCostService $ancillaryCostService,
        private readonly GroupActionService $groupActionService
    ) {
        $this->middleware('permission:invoices.view', ['only' => ['index']]);
        $this->middleware('permission:invoices.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:invoices.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:invoices.delete', ['only' => ['destroy']]);
        $this->middleware('permission:invoices.approve', ['only' => ['changeStatus', 'inactiveInvoices', 'approveInactiveInvoices', 'conflicts', 'showMoreConflictsByType', 'groupAction']]);
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

        $builder->when($request->filled('start_date'),
            fn ($q) => $q->where('date', '>=', convertToGregorian($request->start_date))
        );

        $builder->when($request->filled('end_date'),
            fn ($q) => $q->where('date', '<=', convertToGregorian($request->end_date))
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

        $service_buy = $request->filled('invoice_type') && $request->invoice_type === InvoiceType::BUY->value && $request->filled('service_buy') && $request->service_buy == '1';

        $builder->when($service_buy, fn ($q) => $q->whereHas('items', function ($item) {
            $item->where('itemable_type', Service::class);
        }));

        $builder->when(! $service_buy, fn ($q) => $q->whereHas('items', function ($item) {
            $item->where('itemable_type', Product::class);
        }));

        $statsBuilder = $builder->clone();

        $builder->when($request->filled('status') &&
            in_array($request->status, ['approved', 'unapproved', 'pending', 'approved_inactive', 'rejected', 'ready_to_approve', 'pre_invoice']),
            fn ($invoice) => $invoice->where('status', $request->status)
        );

        $invoices = $builder->paginate(25);

        $statusCounts = $statsBuilder->reorder()
            ->toBase()
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoices->transform(function ($invoice) {
            $invoice->changeStatusValidation = InvoiceService::getChangeStatusValidation($invoice);

            return $invoice;
        });

        $invoices->totalAmount = $invoices->sum('amount');
        $invoices->totalProductsQuantity = $invoices->sum(fn ($invoice) => $invoice->items->where('itemable_type', Product::class)->sum('quantity'));

        return view('invoices.index', compact('invoices', 'statusCounts', 'service_buy'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse
     */
    public function create(Request $request)
    {
        if (empty(config('amir.inventory'))) {
            return redirect()->route('configs.index')->with('error', __('Inventory Subject is not configured. Please set it in configurations.'));
        }

        if (empty(config('amir.cust_subject'))) {
            return redirect()->route('configs.index')->with('error', __('Customer Subject is not configured. Please set it in configurations.'));
        }

        $returnedInvoices = collect();
        $products = collect();
        $services = collect();
        $customers = collect();

        $returnInvoiceTypeMap = [
            'return_buy' => InvoiceType::BUY,
            'return_sell' => InvoiceType::SELL,
        ];

        if (isset($returnInvoiceTypeMap[$request->invoice_type])) {
            // If it's return invoice, we need to load the returned Invoices items (products/services) and customers to form
            $returnInvoiceType = $returnInvoiceTypeMap[$request->invoice_type];

            $returnedInvoices = Invoice::where('invoice_type', $returnInvoiceType)->where('status', InvoiceStatus::APPROVED)->with(['customer', 'items'])->get();

            if ($returnedInvoices->isNotEmpty()) {
                $productIds = $returnedInvoices->flatMap(function ($invoice) {
                    return $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id');
                })->unique();
                $products = Product::with('inventorySubject', 'productGroup')->whereIn('id', $productIds)->get();

                $serviceIds = $returnedInvoices->flatMap(function ($invoice) {
                    return $invoice->items->where('itemable_type', Service::class)->pluck('itemable_id');
                })->unique();
                $services = Service::with('subject', 'serviceGroup')->whereIn('id', $serviceIds)->get();

                $customerIds = $returnedInvoices->pluck('customer.id')->unique();
                $customers = Customer::with('group')->whereIn('id', $customerIds)->get();
            }
        } else {
            $products = Product::with('inventorySubject', 'productGroup')->orderBy('name')->limit(20)->get();
            $services = Service::with('subject', 'serviceGroup')->orderBy('name')->limit(20)->get();
            $customers = Customer::with('group')->orderBy('name')->limit(20)->get();
        }

        $returnedInvoices = $returnedInvoices->map(function ($invoice) { // Format invoices for select box
            return [
                'id' => $invoice->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => trim(($invoice->title ?? '').' - '.$invoice->number, ' -'),
                'type' => 'invoice',
                'customer_id' => $invoice->customer_id,
            ];
        })->values()->all();

        $previousDocumentNumber = floor(Document::max('number') ?? 0);

        $transactions = InvoiceService::prepareTransactions();

        $isServiceBuy = $request->invoice_type === 'buy' && $request->service_buy == '1';

        $total = count($transactions);

        $invoice_type = in_array($request->invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']) ? $request->invoice_type : 'sell';
        $isReturnInvoice = in_array($invoice_type, ['return_buy', 'return_sell'], true);
        $previousInvoiceNumber = floor(Invoice::where('invoice_type', $invoice_type)->max('number') ?? 0);

        return view('invoices.create', compact('returnedInvoices', 'products', 'services', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber', 'invoice_type', 'isServiceBuy', 'isReturnInvoice'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreInvoiceRequest $request)
    {
        $validated = $request->validated();
        $invoiceData = InvoiceService::extractInvoiceData($validated);
        $items = InvoiceService::mapTransactionsToItems($validated['transactions']);

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('invoices.approve');
        }

        $result = $this->invoiceService->createInvoice(auth()->user(), $invoiceData, $items, $approved);

        [$msgType, $msg] = $this->invoiceMessage($result, 'created', $approved);

        $isServiceBuy = $result['invoice']->invoice_type === InvoiceType::BUY && $result['invoice']->items->where('itemable_type', Product::class)->isEmpty();

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type, 'service_buy' => $isServiceBuy ? '1' : null])
            ->with($msgType, $msg);
    }

    public function show(Invoice $invoice)
    {
        $changeStatusValidation = InvoiceService::getChangeStatusValidation($invoice);

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

        return view('invoices.show', compact('invoice', 'changeStatusValidation'));
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

        $returnedInvoices = [];
        if (in_array($invoice->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL]) && $invoice->returned_invoice_id) {
            $returnedInvoice = $invoice->returnedInvoice()->with('customer')->first();

            if ($returnedInvoice) {
                $returnedInvoices = [[
                    'id' => $returnedInvoice->id,
                    'groupId' => 0,
                    'groupName' => 'General',
                    'text' => trim(($returnedInvoice->title ?? '').' - '.$returnedInvoice->number, ' -'),
                    'type' => 'invoice',
                    'customer_id' => $returnedInvoice->customer_id,
                ]];
            }
        }

        $customerIdsForSelect = Customer::orderBy('name')->limit(20)->pluck('id');
        $productIdsForSelect = Product::orderBy('name')->limit(20)->pluck('id');
        $serviceIdsForSelect = Service::orderBy('name')->limit(20)->pluck('id');
        $previousDocumentNumber = floor(Document::max('number') ?? 0);

        $selectedProductIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->unique();

        $selectedServiceIds = $invoice->items->where('itemable_type', Service::class)->pluck('itemable_id')->unique();

        $products = Product::with(['inventorySubject', 'productGroup'])->whereIn('id', $productIdsForSelect->merge($selectedProductIds)->unique())
            ->orderBy('name')->get();

        $services = Service::with(['subject', 'serviceGroup'])->whereIn('id', $serviceIdsForSelect->merge($selectedServiceIds)->unique())
            ->orderBy('name')->get();

        $customers = Customer::with('group')->whereIn('id', $customerIdsForSelect->push($invoice->customer_id)->unique())
            ->orderBy('name')->get();

        // Prepare transactions from invoice items
        $transactions = InvoiceService::prepareTransactions($invoice, 'edit');

        $total = $transactions->count();

        $invoice_type = $invoice->invoice_type;
        $isReturnInvoice = in_array($invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL], true);

        $isServiceBuy = $invoice->invoice_type === InvoiceType::BUY && $invoice->items->where('itemable_type', Product::class)->isEmpty();

        return view('invoices.edit', compact(
            'invoice',
            'returnedInvoices', // for return invoice select box
            'customers',
            'total',
            'products',
            'services',
            'transactions',
            'invoice_type',
            'previousDocumentNumber',
            'isServiceBuy',
            'isReturnInvoice'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(StoreInvoiceRequest $request, Invoice $invoice)
    {
        $validated = $request->validated();
        $invoiceData = InvoiceService::extractInvoiceData($validated);
        $items = InvoiceService::mapTransactionsToItems($validated['transactions'], true);

        if ($invoice->ancillaryCosts()->exists() && $invoice->ancillaryCosts->every(fn ($ac) => $ac->status->isApproved())) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('error', __('Invoice has associated approved ancillary costs and cannot be edited.'));
        }

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('invoices.approve');
        }

        $result = $this->invoiceService->updateInvoice($invoice->id, $invoiceData, $items, $approved);

        [$msgType, $msg] = $this->invoiceMessage($result, 'updated', $approved);

        $isServiceBuy = $result['invoice']->invoice_type === InvoiceType::BUY && $result['invoice']->items->where('itemable_type', Product::class)->isEmpty();

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type, 'service_buy' => $isServiceBuy ? '1' : null])
            ->with($msgType, $msg);
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status->isApproved()) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('error', __('Only unapproved invoices can be deleted.'));
        }

        if ($invoice->ancillaryCosts()->exists() && $invoice->ancillaryCosts->every(fn ($ac) => $ac->status->isApproved())) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('error', __('Invoice has associated approved ancillary costs and cannot be deleted.'));
        }

        InvoiceService::deleteInvoice($invoice->id);

        return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])->with('info', __('Invoice deleted successfully.'));
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

    public function search(Request $request, string $invoice_type)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $invoice_type = $request->invoice_type;

        if (in_array($invoice_type, ['return_buy', 'return_sell'])) {
            $baseType = str_replace('return_', '', $invoice_type);
            $invoice_type = $baseType;
        }

        $q = $validated['q'];

        $invoices = Invoice::where('status', InvoiceStatus::APPROVED)->where('invoice_type', $invoice_type)
            ->where(function ($query) use ($q) {
                $query->where('number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            })->select('id', 'number', 'date', 'title', 'customer_id')->limit(20)->get();

        if ($invoices->isEmpty()) {
            return response()->json([]);
        }

        $options = (object) [
            0 => $invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => trim(($invoice->title ?? '').' - '.$invoice->number, ' -'),
                'title' => $invoice->title,
                'number' => $invoice->number,
                'customer_id' => $invoice->customer_id,
                'type' => 'invoice',
            ])->all(),
        ];

        return response()->json([
            [
                'id' => 'group_invoices',
                'headerGroup' => 'invoice',
                'options' => $options,
            ],
        ]);
    }

    /**
     * Get invoice items for a given invoice. Used when an invoice is selected in return sell or return buy.
     */
    public function getItems(Invoice $invoice)
    {
        $items = $invoice->items()->with('itemable')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->itemable->name,
                'subject' => $item->itemable_type === Product::class ? $item->itemable->inventorySubject->name : ($item->itemable_type === Service::class ? $item->itemable->subject->name : ''),
                'service_id' => $item->itemable_type === Service::class ? $item->itemable_id : null,
                'product_id' => $item->itemable_type === Product::class ? $item->itemable_id : null,
                'inventory_subject_id' => $item->itemable_type === Product::class ? $item->itemable->inventory_subject_id : null,
                'vat' => $item->vat,
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'off' => $item->unit_discount,
                'total' => $item->amount,
                'desc' => $item->description,
            ];
        });

        return response()->json($items);
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

    public function changeStatus(Invoice $invoice, string $status)
    {
        $sellAllowedStatuses = ['approved', 'unapproved', 'ready_to_approve', 'rejected'];
        $defaultAllowedStatuses = ['approved', 'unapproved'];
        $allowedStatuses = $invoice->invoice_type === InvoiceType::SELL ? $sellAllowedStatuses : $defaultAllowedStatuses;

        if (! in_array($status, $allowedStatuses)) {
            return redirect()->route('invoices.index', ['invoice_type' => $invoice->invoice_type])
                ->with('error', __('Invalid status action.'));
        }

        $decision = $this->invoiceService->getChangeStatusDecision($invoice, $status);
        if ($decision->hasErrors()) {
            $error = $decision->messages->first(fn ($m) => $m->type === 'error');

            return redirect()->back()->with('error', $error?->text ?? __('Invalid invoice status.'));
        }

        if ($decision->needsConfirmation && ! request()->has('confirm')) {
            $warning = $decision->messages->first(fn ($m) => $m->type === 'warning');

            return redirect()->back()->with('warning', $warning?->text ?? __('Please confirm your action.'))->with('confirm_invoice_status_change', true)->with('conflicting_invoices', $decision->conflicts->map(fn ($i) => ['id' => $i->id, 'number' => $i->number, 'type' => $i->invoice_type])->values()->all());
        }

        $this->invoiceService->changeInvoiceStatus($invoice, $status);

        $message = $status === 'approved' ? __('Invoice approved successfully.') : __('Invoice unapproved successfully.');

        return redirect()->back()->with('success', __($message));
    }

    public function inactiveInvoices()
    {
        $invoices = Invoice::where('status', InvoiceStatus::APPROVED_INACTIVE)
            ->with(['ancillaryCosts' => function ($query) {
                $query->where('status', InvoiceStatus::APPROVED_INACTIVE);
            }])
            ->orderBy('date')
            ->orderBy('number')
            ->paginate(30);

        $this->validateInvoicesAncillaryCosts($invoices->getCollection());

        return view('invoices.inactive', compact('invoices'));
    }

    public function approveInactiveInvoices()
    {
        $this->groupActionService->approveInactiveInvoices();

        return redirect()->route('invoices.index', ['invoice_type' => InvoiceType::BUY])->with('success', __('Inactive invoices approved successfully.'));
    }

    public function conflicts(Invoice $invoice)
    {
        [$invoicesConflicts, $ancillaryConflicts, $productsConflicts] = $this->groupActionService->findAllConflictsRecursively($invoice, true);
        $conflicts = [
            'invoices' => $invoicesConflicts,
            'ancillaryCosts' => $ancillaryConflicts,
            'products' => $productsConflicts,
        ];

        $allowedToResolve = $conflicts['products']->every(fn ($product) => $product->oversell === 1);

        return view('invoices.conflicts.group', compact('invoice', 'conflicts', 'allowedToResolve'));
    }

    public function showMoreConflictsByType(Invoice $invoice, string $type)
    {
        [$invoicesConflicts, $ancillaryConflicts, $productsConflicts] = $this->groupActionService->findAllConflictsRecursively($invoice, true);

        $conflicts = match ($type) {
            'invoices' => $invoicesConflicts,
            'ancillary' => $ancillaryConflicts,
            'products' => $productsConflicts,
            default => abort(404),
        };

        return view('invoices.conflicts.more', compact('invoice', 'conflicts', 'type'));
    }

    public function groupAction(Invoice $invoice)
    {
        $this->groupActionService->inactivateDependentInvoices($invoice);

        return redirect()->route('invoices.show', $invoice);
    }

    private function validateInvoicesAncillaryCosts($invoices)
    {
        $allAncillaryCosts = $invoices->pluck('ancillaryCosts')->flatten();
        $blockedMap = $this->getBlockedAncillaryCostsMap($allAncillaryCosts);

        $invoices->transform(function ($invoice) use ($blockedMap) {
            $blockedReasons = $invoice->ancillaryCosts
                ->filter(fn ($ac) => isset($blockedMap[$ac->id]))
                ->map(fn ($ac) => $blockedMap[$ac->id])
                ->unique();

            $invoice->allowedAncillaryCostsToResolve = $blockedReasons->isEmpty();
            $invoice->allowedAncillaryCostsToResolveReason = $blockedReasons->implode("\n") ?: null;

            return $invoice;
        });
    }

    private function getBlockedAncillaryCostsMap($ancillaryCosts): array
    {
        $map = [];
        foreach ($ancillaryCosts as $ancillaryCost) {
            $validation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);
            if (! ($validation['allowed'] ?? false)) {
                $map[$ancillaryCost->id] = $validation['message'] ?? $validation['reason'] ?? __('Not allowed to resolve this ancillary cost.');
            }
        }

        return $map;
    }
}
