<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAncillaryCostRequest;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Services\AncillaryCostService;
use Exception;
use Illuminate\Http\Request;

class AncillaryCostController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ancillary-costs.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:ancillary-costs.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:ancillary-costs.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:ancillary-costs.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $ancillaryCosts = AncillaryCost::with('invoice')->orderByDesc('date')->paginate(12);

        $ancillaryCosts->transform(function ($ancillaryCost) {
            $ancillaryCost->changeStatusValidation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);
            $ancillaryCost->editDeleteStatus = AncillaryCostService::getEditDeleteStatus($ancillaryCost);

            return $ancillaryCost;
        });

        $ancillaryCosts->appends($request->query());

        return view('ancillaryCosts.index', compact('ancillaryCosts'));
    }

    public function show(Invoice $invoice, AncillaryCost $ancillaryCost)
    {
        $this->ensureInvoiceMatchesAncillaryCost($invoice, $ancillaryCost);
        $ancillaryCost->load([
            'invoice',
            'document',
            'customer',
            'items.product',
        ]);

        $editDeleteStatus = AncillaryCostService::getEditDeleteStatus($ancillaryCost);
        $changeStatusValidation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);

        return view('ancillaryCosts.show', compact('ancillaryCost', 'editDeleteStatus', 'changeStatusValidation', 'invoice'));
    }

    public function create(?Invoice $invoice = null)
    {
        $invoiceItems = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing()->take(20);

        if ($invoice) {
            $invoiceItems = $invoiceItems->filter(fn ($item) => $item->id === $invoice->id);
        }

        $invoices = $invoiceItems->map(function ($item) { // Format invoices for select box
            return [
                'id' => $item->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $item->number,
                'type' => 'invoice',
            ];
        })->values()->all();

        $customers = Customer::with('group')->orderBy('name', 'asc')->limit(20)->get();
        $ancillaryCost = new AncillaryCost;
        if ($invoice) {
            $ancillaryCost->invoice_id = $invoice->id;
        }
        $ancillaryCostItems = old('ancillaryCosts') ?? [];

        return view('ancillaryCosts.create', compact('invoices', 'customers', 'ancillaryCost', 'ancillaryCostItems', 'invoice'));
    }

    public function store(StoreAncillaryCostRequest $request, ?Invoice $invoice = null)
    {
        $validated = $request->validated();
        $validated['company_id'] = getActiveCompany();

        $validatedInvoicesId = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing()->pluck('id')->toArray();
        if (! in_array($validated['invoice_id'], $validatedInvoicesId)) {
            throw new Exception(__('Ancillary Cost cannot be created.'), 400);
        }

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('ancillary-costs.approve');
        }

        if ($invoice) {
            $validated['invoice_id'] = $invoice->id;
        }

        $result = AncillaryCostService::createAncillaryCost(auth()->user(), $validated, $approved);

        [$msgType, $msg] = $this->ancillaryCostMessage($result, 'created', $approved);

        if ($invoice) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with($msgType, $msg);
        }

        return redirect()->route('ancillary-costs.index')->with($msgType, $msg);
    }

    public function edit(Invoice $invoice, AncillaryCost $ancillaryCost)
    {
        $this->ensureInvoiceMatchesAncillaryCost($invoice, $ancillaryCost);
        $invoices = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing()->take(20)
            ->map(function ($invoice) { // Format invoices for select box
                return [
                    'id' => $invoice->id,
                    'groupId' => 0,
                    'groupName' => 'General',
                    'text' => $invoice->number,
                    'type' => 'invoice',
                ];
            })->push([ // Include current invoice
                'id' => $ancillaryCost->invoice->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $ancillaryCost->invoice->number,
                'type' => 'invoice',
            ])->unique('id')->values()->all();

        // Load ancillary cost items for editing
        $ancillaryCostItems = $ancillaryCost->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'amount' => $item->amount,
            ];
        })->toArray();

        $customerIdsForSelect = Customer::orderBy('name', 'asc')->limit(20)->pluck('id');
        $customers = Customer::with('group')->whereIn('id', $customerIdsForSelect->push($ancillaryCost->customer_id)->unique())
            ->orderBy('name', 'asc')->get();

        // Calculate VAT percentage: (vat_amount / subtotal_before_vat) * 100
        $subtotalBeforeVat = $ancillaryCost['amount'] - $ancillaryCost['vat'];
        $vatPercentage = $subtotalBeforeVat > 0 ? ($ancillaryCost['vat'] / $subtotalBeforeVat) * 100 : 0;
        $ancillaryCost['vat'] = $vatPercentage;

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices', 'customers', 'ancillaryCostItems', 'invoice'));
    }

    public function update(StoreAncillaryCostRequest $request, Invoice $invoice, AncillaryCost $ancillaryCost)
    {
        $this->ensureInvoiceMatchesAncillaryCost($invoice, $ancillaryCost);
        if (AncillaryCostService::getEditDeleteStatus($ancillaryCost)['allowed'] === false) {
            throw new Exception(__('Ancillary Cost cannot be edited.'), 400);
        }

        $validated = $request->validated();
        $validated['company_id'] = getActiveCompany();

        $ancillaryCostInvoiceId = $ancillaryCost->invoice_id;
        $validatedInvoicesId = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing()->pluck('id')->toArray();
        $validatedInvoicesId[] = $ancillaryCostInvoiceId;
        if (! in_array($validated['invoice_id'], $validatedInvoicesId)) {
            throw new Exception(__('Ancillary Cost cannot be created.'), 400);
        }

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('ancillary-costs.approve');
        }

        if ($invoice) {
            $validated['invoice_id'] = $invoice->id;
        }

        $result = AncillaryCostService::updateAncillaryCost(auth()->user(), $ancillaryCost, $validated, $approved);

        [$msgType, $msg] = $this->ancillaryCostMessage($result, 'updated', $approved);

        if ($invoice) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with($msgType, $msg);
        }

        return redirect()->route('ancillary-costs.index')->with($msgType, $msg);
    }

    private function ancillaryCostMessage(array $result, string $action = 'created', bool $approved = false)
    {
        if (! $approved) {
            return [
                'success',
                __("Ancillary Cost {$action} successfully."),
            ];
        }

        $documentMissing = empty($result['document']);

        return [
            $documentMissing ? 'warning' : 'success',
            __("Ancillary Cost {$action} successfully.")
                .($documentMissing
                    ? ' '.__('but it could not be approved due to validation constraints.')
                    : ''
                ),
        ];
    }

    public function destroy(Invoice $invoice, AncillaryCost $ancillaryCost)
    {
        $this->ensureInvoiceMatchesAncillaryCost($invoice, $ancillaryCost);
        if (AncillaryCostService::getEditDeleteStatus($ancillaryCost)['allowed'] === false) {
            throw new Exception(__('Ancillary Cost cannot be deleted.'), 400);
        }

        AncillaryCostService::deleteAncillaryCost($ancillaryCost);

        if ($invoice) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('success', __('Ancillary Cost deleted successfully.'));
        }

        return redirect()->route('ancillary-costs.index')->with('success', __('Ancillary Cost deleted successfully.'));
    }

    public function getBuyInvoiceProducts($invoice_id)
    {
        $invoice = Invoice::findOrFail($invoice_id);

        if (! $invoice) {
            return response()->json(['products' => []]);
        }

        $invoiceItems = $invoice->items()
            ->where('itemable_type', \App\Models\Product::class)
            ->with('itemable')
            ->get();

        $products = collect($invoiceItems)->map(function ($item) {
            return [
                'id' => $item->itemable->id,
                'name' => $item->itemable->name,
                'quantity' => (int) $item->quantity,
            ];
        })->unique('id')->values();

        return response()->json(['products' => $products]);
    }

    public function changeStatus(AncillaryCost $ancillaryCost, string $status, AncillaryCostService $service, ?Invoice $invoice = null)
    {
        $this->ensureInvoiceMatchesAncillaryCost($invoice, $ancillaryCost);
        if (! in_array($status, ['approve', 'unapprove'])) {
            return redirect()->route('ancillary-costs.index')
                ->with('error', __('Invalid status action.'));
        }

        auth()->user()->can('ancillary-costs.approve');

        if (! $service->getChangeStatusValidation($ancillaryCost)['allowed']) {
            redirect()->back()->with('error', $service->getChangeStatusValidation($ancillaryCost)['reason']);
        }

        $service->changeAncillaryCostStatus($ancillaryCost, $status);

        $message = $status === 'approve' ? __('Ancillary Cost approved successfully.') : __('Ancillary Cost unapproved successfully.');

        return redirect()->route('invoices.ancillary-costs.show', [$ancillaryCost->invoice_id, $ancillaryCost])->with('success', value: __($message));
    }

    private function ensureInvoiceMatchesAncillaryCost(?Invoice $invoice, AncillaryCost $ancillaryCost): void
    {
        if ($invoice && $ancillaryCost->invoice_id !== $invoice->id) {
            abort(404);
        }
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

        $options = (object) [
            0 => $customers->map(fn ($customer) => [
                'id' => $customer->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $customer->name,
                'type' => 'customer',
            ])->all(),
        ];

        if ($customers->isNotEmpty()) {
            $results[] = [
                'id' => 'group_customers',
                'headerGroup' => 'customer',
                'options' => $options,
            ];
        }

        return response()->json($results);
    }

    public function searchInvoice(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $invoices = Invoice::where('number', 'like', "%{$q}%")->select('id', 'number', 'date')->limit(20)->get();

        if ($invoices->isEmpty()) {
            return response()->json([]);
        }

        $options = (object) [
            0 => $invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $invoice->number,
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
}
