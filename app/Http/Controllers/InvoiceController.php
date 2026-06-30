<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filters\InvoiceFilter;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\AncillaryCostService;
use App\Services\FiscalYearTransferService;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use App\Services\PaymentService;
use DB;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly AncillaryCostService $ancillaryCostService,
        private readonly GroupActionService $groupActionService
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return View|Factory
     */
    public function index(Request $request, InvoiceFilter $filter)
    {
        $builder = Invoice::with(['customer', 'document', 'voidInvoice', 'payments'])
            ->filter($filter);

        $builder->when(in_array($request->invoice_type, [InvoiceType::SELL->value, InvoiceType::VOID->value]),
            fn ($q) => $q->with('latestMoadianHistory')
        );

        $statsBuilder = $builder->clone();

        $filter->applyStatus($builder);

        $invoices = $builder->orderByDesc('date')
            ->orderByDesc('number')
            ->paginate(25);

        $statusCounts = $statsBuilder->reorder()
            ->toBase()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoices->transform(function ($invoice) {
            $invoice->changeStatusValidation = InvoiceService::getChangeStatusValidation($invoice);

            return $invoice;
        });

        $totalsBuilder = $statsBuilder->clone();
        $invoices->totalAmount = $totalsBuilder->toBase()->sum('amount');

        $itemTotals = DB::table('invoice_items')
            ->whereIn('invoice_id', $statsBuilder->clone()->toBase()->select('id'))
            ->selectRaw('itemable_type, SUM(quantity) as total_quantity')
            ->groupBy('itemable_type')
            ->pluck('total_quantity', 'itemable_type');

        $invoices->totalProductsQuantity = $itemTotals[Product::class] ?? 0;
        $invoices->totalServicesQuantity = $itemTotals[Service::class] ?? 0;

        $service_buy = $filter->isServiceBuy();

        return view('invoices.index', compact('invoices', 'statusCounts', 'service_buy'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View|Factory|RedirectResponse
     */
    public function create(Request $request)
    {
        if (empty(config('amir.inventory'))) {
            return redirect()->route('configs.index')->with('error', __('Inventory Subject is not configured. Please set it in configurations.'));
        }

        if (empty(config('amir.cust_subject'))) {
            return redirect()->route('configs.index')->with('error', __('Customer Subject is not configured. Please set it in configurations.'));
        }

        $returnInvoices = collect();
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

            $returnInvoices = Invoice::where('invoice_type', $returnInvoiceType)->where('status', InvoiceStatus::APPROVED)->with(['customer', 'items'])->get();

            if ($request->filled('service_buy')) {
                $returnInvoices = $returnInvoices->filter(function ($invoice) {
                    return $invoice->items->where('itemable_type', Product::class)->isEmpty();
                });
            }

            if (! $request->filled('service_buy') && $returnInvoiceType === InvoiceType::BUY) {
                $returnInvoices = $returnInvoices->filter(function ($invoice) {
                    return $invoice->items->where('itemable_type', Service::class)->isEmpty();
                });
            }

            if ($returnInvoices->isNotEmpty()) {
                $productIds = $returnInvoices->flatMap(function ($invoice) {
                    return $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id');
                })->unique();
                $products = Product::with('inventorySubject', 'productGroup')->whereIn('id', $productIds)->get();

                $serviceIds = $returnInvoices->flatMap(function ($invoice) {
                    return $invoice->items->where('itemable_type', Service::class)->pluck('itemable_id');
                })->unique();
                $services = Service::with('subject', 'serviceGroup')->whereIn('id', $serviceIds)->get();

                $customerIds = $returnInvoices->pluck('customer.id')->unique();
                $customers = Customer::with('group')->whereIn('id', $customerIds)->get();
            }
        } else {
            $products = Product::with('inventorySubject', 'productGroup')->orderBy('name')->limit(20)->get();
            $services = Service::with('subject', 'serviceGroup')->orderBy('name')->limit(20)->get();
            $customers = Customer::with('group')->orderBy('name')->limit(20)->get();
        }

        $returnInvoices = $returnInvoices->map(function ($invoice) { // Format invoices for select box
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
        $isReturnServiceBuy = $request->invoice_type === 'return_buy' && $request->service_buy == '1';

        $total = count($transactions);

        $invoice_type = in_array($request->invoice_type, ['buy', 'sell', 'return_buy', 'return_sell']) ? $request->invoice_type : 'sell';
        $isReturnInvoice = in_array($invoice_type, ['return_buy', 'return_sell'], true);
        $prefilledReturnedInvoiceId = null;
        $lockReturnedInvoiceSelection = false;

        if ($isReturnInvoice && $request->filled('returned_invoice_id')) {
            $requestedReturnedInvoiceId = (int) $request->returned_invoice_id;
            $isValidReturnInvoice = collect($returnInvoices)->contains(fn ($returnInvoice) => (int) ($returnInvoice['id'] ?? 0) === $requestedReturnedInvoiceId);

            if ($isValidReturnInvoice) {
                $prefilledReturnedInvoiceId = $requestedReturnedInvoiceId;
                $lockReturnedInvoiceSelection = true;
            }
        }

        $previousInvoiceNumber = floor(Invoice::where('invoice_type', $invoice_type)->max('number') ?? 0);

        return view('invoices.create', compact('returnInvoices', 'products', 'services', 'customers', 'transactions', 'total', 'previousInvoiceNumber', 'previousDocumentNumber', 'invoice_type', 'isServiceBuy', 'isReturnServiceBuy', 'isReturnInvoice', 'prefilledReturnedInvoiceId', 'lockReturnedInvoiceSelection'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
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

        $isServiceBuy = in_array($result['invoice']->invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_BUY]) && $result['invoice']->items->where('itemable_type', Product::class)->isEmpty();

        return redirect()
            ->route('invoices.index', ['invoice_type' => $result['invoice']->invoice_type, 'service_buy' => $isServiceBuy ? '1' : null])
            ->with($msgType, $msg);
    }

    public function show(Invoice $invoice, PaymentService $paymentService)
    {
        $changeStatusValidation = InvoiceService::getChangeStatusValidation($invoice);

        $isServiceBuy = $invoice->invoice_type === InvoiceType::BUY && $invoice->items->where('itemable_type', Product::class)->isEmpty();
        $isReturnServiceBuy = $invoice->invoice_type === InvoiceType::RETURN_BUY && $invoice->items->where('itemable_type', Product::class)->isEmpty();
        $isMoadianSendable = in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::VOID]);

        $invoice->load([
            'customer',
            'document',
            'document.transactions',
            'items',
            'voidInvoice',
            'voidedInvoice',
            'ancillaryCosts',
            'ancillaryCosts.customer',
            'ancillaryCosts.document',
            'ancillaryCosts.items',
            'moadianHistories',
            'payments.document.transactions.subject',
            'payments.payer.subject',
            'payments.creator',
        ]);

        $paymentDecision = $paymentService->validateInvoicePayment($invoice);
        $settlementSubjects = $paymentService->settlementSubjects();
        $paidAmount = $paymentService->paidAmount($invoice);
        $remainingAmount = $paymentService->remainingAmount($invoice);

        $ancillaryCostProductIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->unique()->values()->all();
        $canCreateAncillaryCost = $invoice->invoice_type === InvoiceType::BUY && ! $isServiceBuy && empty(InvoiceService::notAllowedInvoiceForAncillaryCosts($invoice, $ancillaryCostProductIds));

        $fiscalYears = Company::whereHas('users', function ($q) {
            $q->where('users.id', auth()->id());
        })->where('id', '!=', getActiveCompany())->get();

        return view('invoices.show', compact('invoice', 'changeStatusValidation', 'isServiceBuy', 'isReturnServiceBuy', 'isMoadianSendable', 'paymentDecision', 'settlementSubjects', 'paidAmount', 'remainingAmount', 'fiscalYears', 'canCreateAncillaryCost'));
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
     * @return View|Factory|RedirectResponse
     */
    public function edit(Invoice $invoice)
    {
        if ($invoice->invoice_type->isVoid()) {
            return redirect()->back()->with('error', __('Editing is not allowed for void invoices.'));
        }

        $invoice->load('customer', 'document.transactions', 'items'); // Eager load relationships

        $returnInvoices = [];
        if (in_array($invoice->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL]) && $invoice->returned_invoice_id) {
            $returnedInvoice = $invoice->returnedInvoice()->with('customer')->first();

            if ($returnedInvoice) {
                $returnInvoices = [[
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
        $isReturnServiceBuy = $invoice->invoice_type === InvoiceType::RETURN_BUY && $invoice->items->where('itemable_type', Product::class)->isEmpty();

        return view('invoices.edit', compact(
            'invoice',
            'returnInvoices', // for return invoice select box
            'customers',
            'total',
            'products',
            'services',
            'transactions',
            'invoice_type',
            'previousDocumentNumber',
            'isServiceBuy',
            'isReturnServiceBuy',
            'isReturnInvoice'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return RedirectResponse
     */
    public function update(StoreInvoiceRequest $request, Invoice $invoice)
    {
        if ($invoice->invoice_type->isVoid()) {
            return redirect()->back()->with('error', __('Editing is not allowed for void invoices.'));
        }

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

        $isServiceBuy = in_array($result['invoice']->invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_BUY]) && $result['invoice']->items->where('itemable_type', Product::class)->isEmpty();

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

        $invoice_type = InvoiceType::from($invoice_type);

        if (in_array($invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL])) {
            $baseType = str_replace('return_', '', $invoice_type->value);
            $invoice_type = InvoiceType::from($baseType);
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

        if ($invoice->status->isPartiallyPaid() || $invoice->status->isPaid()) {
            return redirect()->back()->with('error', __('Remove the recorded payments before changing the invoice status.'));
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

    public function transfer(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate(['target_company_id' => 'required|integer|exists:companies,id']);

        if (! Auth::user()->companies->contains((int) $request->target_company_id)) {
            abort(403);
        }

        if ((int) $request->target_company_id === getActiveCompany()) {
            return redirect()->route('invoices.show', $invoice)->with('error', __('Cannot transfer to the same fiscal year.'));
        }

        $result = FiscalYearTransferService::transferInvoice($invoice, $request->target_company_id, $request->user());

        if (! $result['success']) {
            return redirect()->route('invoices.show', $invoice)->withErrors($result['errors']);
        }

        $redirect = redirect()->route('invoices.show', $invoice)
            ->with('success', __('Invoice transferred successfully to the target fiscal year.'));

        if (! empty($result['warnings'])) {
            $redirect = $redirect->with('warning', $result['warnings']);
        }

        return $redirect;
    }

    public function sendMoadian(Invoice $invoice, MoadianService $moadianService)
    {
        $decision = $moadianService->validateSendMoadian($invoice);

        if ($decision->hasErrors()) {
            return redirect()->route('invoices.show', $invoice)->withErrors($decision->messages->pluck('text')->all());
        }

        $success = $moadianService->sendInvoice($invoice);

        if ($success) {
            return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice sent to Moadian successfully.'));
        }

        return redirect()->route('invoices.show', $invoice)->with('error', __('Failed to send invoice to Moadian. Please try again.'));
    }

    public function showVoidForm(Invoice $invoice)
    {
        $voidInvoiceDecision = $this->invoiceService->validateVoidingInvoice($invoice);
        $previousInvoiceNumber = floor(Invoice::where('invoice_type', InvoiceType::VOID)->max('number') ?? 0);

        if ($voidInvoiceDecision->hasErrors()) {
            return redirect()->route('invoices.show', $invoice)->with('error', $voidInvoiceDecision->messages->pluck('text')->all());
        }

        return view('invoices.forms.void', compact('invoice', 'previousInvoiceNumber'));
    }

    public function voidInvoice(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'invoice_number' => ['required'],
        ]);

        $number = convertToInt($validated['invoice_number']);
        if (! is_numeric($number) || $number < 1) {
            return back()->with('error', __('Invoice Number is invalid.'));
        }

        $date = convertToGregorian($validated['date']);

        $voidInvoiceDecision = $this->invoiceService->validateVoidingInvoice($invoice, $date);

        if ($voidInvoiceDecision->hasErrors()) {
            return redirect()->back()->with('error', $voidInvoiceDecision->messages->pluck('text')->all());
        }

        $voidInvoice = $this->invoiceService->voidInvoice($invoice, auth()->user(), $date, $number);

        [$msgType, $msg] = $this->invoiceMessage($voidInvoice, 'voided', true);

        return redirect()->route('invoices.show', $invoice)->with($msgType, $msg);
    }
}
