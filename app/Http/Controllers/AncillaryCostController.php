<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAncillaryCostRequest;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Services\AncillaryCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AncillaryCostController extends Controller
{
    public function index(Request $request)
    {
        // Group by invoice, description and date; sum the amount for display per row
        $ancillaryCosts = AncillaryCost::select(
            'invoice_id',
            'description',
            'date',
            DB::raw('SUM(amount) as amount'),
            DB::raw('MIN(id) as id') // representative id for edit/delete routes
        )
            ->with('invoice')
            ->groupBy('invoice_id', 'description', 'date')
            ->orderByDesc('date')
            ->paginate(12);

        $ancillaryCosts->appends($request->query());

        return view('ancillaryCosts.index', compact('ancillaryCosts'));
    }

    public function create()
    {
        // Ancillary costs are applicable to SELL invoices per inventory accounting guide
        $invoices = Invoice::select('id', 'number')
            ->where('invoice_type', 'buy')
            ->orderByDesc('date')
            ->get();
        $ancillaryCost = new AncillaryCost;
        $ancillaryCosts = old('ancillaryCosts') ?? $this->preparedAncillaryCosts(collect([new AncillaryCost]));

        return view('ancillaryCosts.create', compact('invoices', 'ancillaryCost', 'ancillaryCosts'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        $validated = $request->validated();

        // Create one ancillary cost per product using the service (which updates average cost)
        if (! empty($validated['ancillaryCosts'])) {
            foreach ($validated['ancillaryCosts'] as $costData) {
                AncillaryCostService::createAncillaryCost([
                    'invoice_id' => $validated['invoice_id'],
                    'date' => $validated['date'],
                    'product_id' => $costData['product_id'],
                    'description' => $validated['description'],
                    'amount' => $costData['amount'],
                ]);
            }
        }

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    public function edit(AncillaryCost $ancillaryCost)
    {
        $invoices = Invoice::select('id', 'number')->where('invoice_type', 'buy')->orderByDesc('date')->get();

        // Get all ancillary costs for the same invoice to allow editing
        $ancillaryCostsCollection = AncillaryCost::where('invoice_id', $ancillaryCost->invoice_id)->get();
        $ancillaryCosts = $this->preparedAncillaryCosts($ancillaryCostsCollection);

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices', 'ancillaryCosts'));
    }

    public function update(StoreAncillaryCostRequest $request, AncillaryCost $ancillaryCost)
    {
        $validated = $request->validated();

        // Replace the existing ancillary costs of this invoice: reverse previous distribution then recreate
        $existing = AncillaryCost::where('invoice_id', $validated['invoice_id'])->get();
        foreach ($existing as $existingCost) {
            AncillaryCostService::deleteAncillaryCost($existingCost->id);
        }

        if (! empty($validated['ancillaryCosts'])) {
            foreach ($validated['ancillaryCosts'] as $costData) {
                AncillaryCostService::createAncillaryCost([
                    'invoice_id' => $validated['invoice_id'],
                    'date' => $validated['date'],
                    'product_id' => $costData['product_id'],
                    'description' => $validated['description'],
                    'amount' => $costData['amount'],
                ]);
            }
        }

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost updated successfully.'));
    }

    public function destroy(AncillaryCost $ancillaryCost)
    {
        // Delete the whole group (same invoice_id, date and description) to keep index grouped behavior consistent
        $groupQuery = AncillaryCost::where('invoice_id', $ancillaryCost->invoice_id)
            ->where('date', $ancillaryCost->date)
            ->where('description', $ancillaryCost->description);

        $groupQuery->get()->each(function ($cost) {
            AncillaryCostService::deleteAncillaryCost($cost->id);
        });

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost deleted successfully.'));
    }

    public function getInvoiceProducts($invoice_id)
    {
        $invoice = Invoice::with('items.product')->findOrFail($invoice_id);
        $products = collect($invoice->items)->map(function ($item) {
            return [
                'id' => $item->product->id,
                'name' => $item->product->name,
            ];
        })->unique('id')->values();

        return response()->json(['products' => $products]);
    }

    private function preparedAncillaryCosts($ancillaryCosts)
    {
        return $ancillaryCosts->map(function ($ancillaryCost) {
            return [
                'product_id' => $ancillaryCost->product_id ?? '',
                'description' => $ancillaryCost->description?->value ?? '',
                'amount' => $ancillaryCost->amount ?? 0,
            ];
        })->toArray();
    }
}
