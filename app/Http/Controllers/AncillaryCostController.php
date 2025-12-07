<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAncillaryCostRequest;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AncillaryCostService;
use Exception;
use Illuminate\Http\Request;

class AncillaryCostController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ancillary-costs.view', ['only' => ['index']]);
        $this->middleware('permission:ancillary-costs.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:ancillary-costs.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:ancillary-costs.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $ancillaryCosts = AncillaryCost::with('invoice')->orderByDesc('date')->paginate(12);

        $ancillaryCosts->appends($request->query());

        return view('ancillaryCosts.index', compact('ancillaryCosts'));
    }

    public function create()
    {
        $invoices = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing();
        $customers = Customer::all();
        $ancillaryCost = new AncillaryCost;
        $ancillaryCostItems = old('ancillaryCosts') ?? [];

        return view('ancillaryCosts.create', compact('invoices', 'customers', 'ancillaryCost', 'ancillaryCostItems'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        $validated = $request->validated();
        $validated['company_id'] = session('active-company-id');

        $validatedInvoicesId = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing()->pluck('id')->toArray();
        if (! in_array($validated['invoice_id'], $validatedInvoicesId)) {
            throw new Exception(__('Ancillary Cost cannot be created.'), 400);
        }

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('ancillary-costs.approve');
        }

        AncillaryCostService::createAncillaryCost(auth()->user(), $validated, $approved);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    public function edit(AncillaryCost $ancillaryCost)
    {
        $invoices = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing();

        // Load ancillary cost items for editing
        $ancillaryCostItems = $ancillaryCost->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'amount' => $item->amount,
            ];
        })->toArray();

        $customers = Customer::all();

        // Calculate VAT percentage: (vat_amount / subtotal_before_vat) * 100
        $subtotalBeforeVat = $ancillaryCost['amount'] - $ancillaryCost['vat'];
        $vatPercentage = $subtotalBeforeVat > 0 ? ($ancillaryCost['vat'] / $subtotalBeforeVat) * 100 : 0;
        $ancillaryCost['vat'] = $vatPercentage;

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices', 'customers', 'ancillaryCostItems'));
    }

    public function update(StoreAncillaryCostRequest $request, AncillaryCost $ancillaryCost)
    {
        if (AncillaryCostService::getEditDeleteStatus($ancillaryCost)['allowed'] === false) {
            throw new Exception(__('Ancillary Cost cannot be edited.'), 400);
        }

        $validated = $request->validated();
        $validated['company_id'] = session('active-company-id');

        $approved = false;
        if ($request->has('approve')) {
            $approved = true;
            auth()->user()->can('ancillary-costs.approve');
        }

        AncillaryCostService::updateAncillaryCost(auth()->user(), $ancillaryCost, $validated, $approved);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost updated successfully.'));
    }

    public function destroy(AncillaryCost $ancillaryCost)
    {
        if (AncillaryCostService::getEditDeleteStatus($ancillaryCost)['allowed'] === false) {
            throw new Exception(__('Ancillary Cost cannot be deleted.'), 400);
        }

        AncillaryCostService::deleteAncillaryCost($ancillaryCost);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost deleted successfully.'));
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

    public function changeStatus(AncillaryCost $ancillaryCost, string $status, AncillaryCostService $service)
    {
        if (! in_array($status, ['approve', 'unapprove'])) {
            return redirect()->route('ancillary-costs.index')
                ->with('error', __('Invalid status action.'));
        }

        if ($status === 'approve') {
            auth()->user()->can('ancillary-costs.approve');
        }

        try {
            $service->changeAncillaryCostStatus($ancillaryCost, $status);

            $message = $status === 'approve' ? __('Ancillary Cost approved successfully.') : __('Ancillary Cost unapproved successfully.');

            return redirect()->route('ancillary-costs.index')
                ->with('success', __($message));
        } catch (Exception $e) {
            return redirect()->route('ancillary-costs.index')
                ->with('error', $e->getMessage());
        }
    }
}
