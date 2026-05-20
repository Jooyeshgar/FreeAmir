<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Collection;

class WarehouseDashboardService
{
    /**
     * Build the read-only warehouse dashboard data.
     *
     * Accounting figures are intentionally optional so users with only product
     * access never receive financial values in the rendered page payload.
     */
    public function dashboard(bool $includeAccounting = false): array
    {
        $months = $this->months();
        $products = Product::query()
            ->with('productGroup:id,name')
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
                'group',
                'quantity',
                'quantity_warning',
                'oversell',
                'selling_price',
                'average_cost',
            ]);

        $approvedItems = $this->approvedInvoiceItems();
        $productItems = $approvedItems->where('itemable_type', Product::class);
        $serviceItems = $approvedItems->where('itemable_type', Service::class);

        $monthlyMovement = $this->monthlyStockMovement($productItems, $months);
        $monthlySalesUnits = $this->monthlySalesUnits($approvedItems, $months);

        return [
            'periodLabel' => config('active-company-fiscal-year') ?? toEnglish(jdate('Y')),
            'inventory' => $this->inventorySummary($products),
            'sales' => $this->salesSummary($productItems, $serviceItems),
            'workflow' => $this->workflowSummary(),
            'monthlyMovement' => $monthlyMovement,
            'monthlySalesUnits' => $monthlySalesUnits,
            'topSellingItems' => $this->topSellingItems($approvedItems),
            'lowStockProducts' => $this->lowStockProducts($products),
            'accounting' => $includeAccounting
                ? $this->accountingSummary($products, $productItems, $serviceItems, $months)
                : null,
        ];
    }

    private function inventorySummary(Collection $products): array
    {
        return [
            'productsCount' => $products->count(),
            'servicesCount' => Service::count(),
            'totalQuantity' => (float) $products->sum(fn (Product $product) => (float) $product->quantity),
            'lowStockCount' => $this->lowStockCollection($products)->count(),
            'negativeStockCount' => $products->filter(fn (Product $product) => (float) $product->quantity < 0)->count(),
            'oversellEnabledCount' => $products->filter(fn (Product $product) => (bool) $product->oversell)->count(),
        ];
    }

    private function salesSummary(Collection $productItems, Collection $serviceItems): array
    {
        $netProductUnits = $productItems->sum(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) * (float) $item->quantity);
        $netServiceUnits = $serviceItems->sum(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) * (float) $item->quantity);
        $returnedProductUnits = $productItems
            ->filter(fn (InvoiceItem $item) => in_array($item->invoice->invoice_type, [InvoiceType::RETURN_SELL, InvoiceType::VOID], true))
            ->sum(fn (InvoiceItem $item) => (float) $item->quantity);

        return [
            'approvedSellInvoices' => Invoice::where('status', InvoiceStatus::APPROVED)
                ->where('invoice_type', InvoiceType::SELL)
                ->count(),
            'netProductUnits' => (float) $netProductUnits,
            'netServiceUnits' => (float) $netServiceUnits,
            'returnedProductUnits' => (float) $returnedProductUnits,
        ];
    }

    private function workflowSummary(): array
    {
        return [
            'readyToApproveInvoices' => Invoice::where('status', InvoiceStatus::READY_TO_APPROVE)->count(),
            'unapprovedInvoices' => Invoice::whereIn('status', [
                InvoiceStatus::PENDING,
                InvoiceStatus::PRE_INVOICE,
                InvoiceStatus::UNAPPROVED,
                InvoiceStatus::REJECTED,
            ])->count(),
            'unapprovedAncillaryCosts' => AncillaryCost::where('status', '!=', InvoiceStatus::APPROVED)->count(),
        ];
    }

    private function accountingSummary(Collection $products, Collection $productItems, Collection $serviceItems, array $months): array
    {
        $productSales = $this->signedSalesAmount($productItems);
        $serviceSales = $this->signedSalesAmount($serviceItems);
        $productCogs = $this->signedProductCogs($productItems);
        $productGrossProfit = $productSales - $productCogs;

        return [
            'inventoryValue' => $products->sum(fn (Product $product) => (float) $product->quantity * (float) $product->average_cost),
            'netSales' => $productSales + $serviceSales,
            'productSales' => $productSales,
            'serviceSales' => $serviceSales,
            'productGrossProfit' => $productGrossProfit,
            'grossMargin' => $productSales != 0.0 ? ($productGrossProfit / $productSales) * 100 : 0,
            'purchaseValue' => $this->signedPurchaseAmount($productItems),
            'approvedAncillaryCosts' => (float) AncillaryCost::where('status', InvoiceStatus::APPROVED)->sum('amount'),
            'monthlyNetSales' => $this->monthlyNetSales($productItems->concat($serviceItems), $months),
            'monthlyProductGrossProfit' => $this->monthlyProductGrossProfit($productItems, $months),
            'topInventoryValueProducts' => $this->topInventoryValueProducts($products),
        ];
    }

    private function approvedInvoiceItems(): Collection
    {
        return InvoiceItem::query()
            ->whereHas('invoice', fn ($query) => $query
                ->where('status', InvoiceStatus::APPROVED)
                ->whereIn('invoice_type', [
                    InvoiceType::BUY,
                    InvoiceType::SELL,
                    InvoiceType::RETURN_BUY,
                    InvoiceType::RETURN_SELL,
                    InvoiceType::VOID,
                ]))
            ->with([
                'invoice:id,date,invoice_type,status,number',
                'itemable',
            ])
            ->get();
    }

    private function monthlyStockMovement(Collection $productItems, array $months): array
    {
        $incoming = array_fill_keys(array_values($months), 0.0);
        $outgoing = array_fill_keys(array_values($months), 0.0);
        $net = array_fill_keys(array_values($months), 0.0);

        foreach ($productItems as $item) {
            $month = $this->monthName($item);
            if ($month === null) {
                continue;
            }

            $quantity = (float) $item->quantity;
            $stockSign = $this->stockSign($item->invoice->invoice_type);

            if ($stockSign > 0) {
                $incoming[$month] += $quantity;
            } elseif ($stockSign < 0) {
                $outgoing[$month] += $quantity;
            }

            $net[$month] += $stockSign * $quantity;
        }

        return [
            'incoming' => $this->roundedSeries($incoming),
            'outgoing' => $this->roundedSeries($outgoing),
            'net' => $this->roundedSeries($net),
        ];
    }

    private function monthlySalesUnits(Collection $items, array $months): array
    {
        $products = array_fill_keys(array_values($months), 0.0);
        $services = array_fill_keys(array_values($months), 0.0);

        foreach ($items as $item) {
            $salesSign = $this->salesSign($item->invoice->invoice_type);
            if ($salesSign === 0) {
                continue;
            }

            $month = $this->monthName($item);
            if ($month === null) {
                continue;
            }

            $quantity = $salesSign * (float) $item->quantity;
            if ($item->itemable_type === Product::class) {
                $products[$month] += $quantity;
            } elseif ($item->itemable_type === Service::class) {
                $services[$month] += $quantity;
            }
        }

        return [
            'products' => $this->roundedSeries($products),
            'services' => $this->roundedSeries($services),
        ];
    }

    private function monthlyNetSales(Collection $items, array $months): array
    {
        $sales = array_fill_keys(array_values($months), 0.0);

        foreach ($items as $item) {
            $salesSign = $this->salesSign($item->invoice->invoice_type);
            if ($salesSign === 0) {
                continue;
            }

            $month = $this->monthName($item);
            if ($month !== null) {
                $sales[$month] += $salesSign * $this->netAmount($item);
            }
        }

        return $this->roundedSeries($sales);
    }

    private function monthlyProductGrossProfit(Collection $productItems, array $months): array
    {
        $profit = array_fill_keys(array_values($months), 0.0);

        foreach ($productItems as $item) {
            $salesSign = $this->salesSign($item->invoice->invoice_type);
            if ($salesSign === 0) {
                continue;
            }

            $month = $this->monthName($item);
            if ($month !== null) {
                $profit[$month] += $salesSign * ($this->netAmount($item) - $this->productCogs($item));
            }
        }

        return $this->roundedSeries($profit);
    }

    private function topSellingItems(Collection $items): Collection
    {
        return $items
            ->filter(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) !== 0)
            ->groupBy(fn (InvoiceItem $item) => $item->itemable_type.'-'.$item->itemable_id)
            ->map(function (Collection $group) {
                $first = $group->first();
                $quantity = $group->sum(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) * (float) $item->quantity);
                $amount = $group->sum(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) * $this->netAmount($item));

                return [
                    'id' => $first->itemable_id,
                    'name' => $first->itemable?->name ?? __('Unknown'),
                    'code' => $first->itemable?->code ?? '-',
                    'type' => $first->itemable_type === Product::class ? __('Product') : __('Services'),
                    'route' => $first->itemable_type === Product::class ? 'products.show' : 'services.show',
                    'quantity' => (float) $quantity,
                    'amount' => (float) $amount,
                ];
            })
            ->filter(fn (array $item) => $item['quantity'] > 0)
            ->sortByDesc('quantity')
            ->take(8)
            ->values();
    }

    private function lowStockProducts(Collection $products): Collection
    {
        return $this->lowStockCollection($products)
            ->sortBy(fn (Product $product) => (float) $product->quantity)
            ->take(8)
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'group' => $product->productGroup?->name ?? '-',
                'quantity' => (float) $product->quantity,
                'quantityWarning' => (float) $product->quantity_warning,
            ])
            ->values();
    }

    private function lowStockCollection(Collection $products): Collection
    {
        return $products->filter(fn (Product $product) => $product->quantity_warning !== null
            && (float) $product->quantity_warning > 0
            && (float) $product->quantity <= (float) $product->quantity_warning);
    }

    private function topInventoryValueProducts(Collection $products): Collection
    {
        return $products
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'quantity' => (float) $product->quantity,
                'averageCost' => (float) $product->average_cost,
                'value' => (float) $product->quantity * (float) $product->average_cost,
            ])
            ->filter(fn (array $product) => $product['value'] > 0)
            ->sortByDesc('value')
            ->take(6)
            ->values();
    }

    private function signedSalesAmount(Collection $items): float
    {
        return (float) $items->sum(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) * $this->netAmount($item));
    }

    private function signedPurchaseAmount(Collection $productItems): float
    {
        return (float) $productItems->sum(fn (InvoiceItem $item) => $this->purchaseSign($item->invoice->invoice_type) * $this->netAmount($item));
    }

    private function signedProductCogs(Collection $productItems): float
    {
        return (float) $productItems->sum(fn (InvoiceItem $item) => $this->salesSign($item->invoice->invoice_type) * $this->productCogs($item));
    }

    private function netAmount(InvoiceItem $item): float
    {
        return (float) $item->amount - (float) ($item->vat ?? 0);
    }

    private function productCogs(InvoiceItem $item): float
    {
        return (float) ($item->cog_after ?? 0) * (float) $item->quantity;
    }

    private function monthName(InvoiceItem $item): ?string
    {
        $date = $item->invoice?->date;
        if (! $date) {
            return null;
        }

        $month = (int) toEnglish(jdate('m', $date->timestamp));

        return $this->months()[$month] ?? null;
    }

    private function stockSign(InvoiceType $invoiceType): int
    {
        return match ($invoiceType) {
            InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID => 1,
            InvoiceType::SELL, InvoiceType::RETURN_BUY => -1,
        };
    }

    private function salesSign(InvoiceType $invoiceType): int
    {
        return match ($invoiceType) {
            InvoiceType::SELL => 1,
            InvoiceType::RETURN_SELL, InvoiceType::VOID => -1,
            default => 0,
        };
    }

    private function purchaseSign(InvoiceType $invoiceType): int
    {
        return match ($invoiceType) {
            InvoiceType::BUY => 1,
            InvoiceType::RETURN_BUY => -1,
            default => 0,
        };
    }

    private function roundedSeries(array $series): array
    {
        return array_map(fn (float $value) => round($value, 2), $series);
    }

    private function months(): array
    {
        return [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];
    }
}
