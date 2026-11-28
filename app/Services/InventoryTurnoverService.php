<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryTurnoverService
{
    private const IMPORT_TYPES = [InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID];

    private const EXPORT_TYPES = [InvoiceType::SELL, InvoiceType::RETURN_BUY];

    public function report(array $rawFilters = []): array
    {
        $filters = $this->validateFilters($rawFilters);

        $products = Product::query()
            ->whereNotNull('inventory_subject_id')
            ->when($filters['product_group'], fn ($query, int $groupId) => $query->where('group', $groupId))
            ->when($filters['warehouse_id'], fn ($query, int $warehouseId) => $query->whereHas('warehouseStocks', fn ($stockQuery) => $stockQuery->where('warehouse_id', $warehouseId)))
            ->with('inventorySubject:id,code,name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'inventory_subject_id']);

        $productIds = $products->pluck('id')->all();
        $subjectIds = $products->pluck('inventory_subject_id')->all();
        $quantities = $this->movementQuantities($productIds, $filters);
        $balances = $this->postedMovementBalances($subjectIds, $filters);
        $transfers = $this->warehouseTransferMovements($productIds, $filters);

        $rows = $products->map(function (Product $product) use ($quantities, $balances, $transfers): array {
            $quantity = $quantities[$product->id] ?? ['opening' => 0.0, 'imported' => 0.0, 'exported' => 0.0];
            $balance = $balances[$product->inventory_subject_id] ?? ['opening' => 0.0, 'imported' => 0.0, 'exported' => 0.0];
            foreach (['opening', 'imported', 'exported'] as $bucket) {
                $quantity[$bucket] += $transfers[$product->id][$bucket.'_quantity'] ?? 0;
                $balance[$bucket] += $transfers[$product->id][$bucket.'_balance'] ?? 0;
            }
            $openingBalance = $balance['opening'];
            $importedBalance = $balance['imported'];
            $exportedBalance = $balance['exported'];

            return [
                'product_id' => $product->id,
                'product_code' => $product->code,
                'subject_id' => $product->inventory_subject_id,
                'subject_code' => $product?->inventorySubject->code,
                'product_name' => $product->name,
                'opening_quantity' => $quantity['opening'],
                'opening_balance' => $openingBalance,
                'imported_quantity' => $quantity['imported'],
                'imported_balance' => $importedBalance,
                'exported_quantity' => $quantity['exported'],
                'exported_balance' => $exportedBalance,
                'remaining_quantity' => $quantity['opening'] + $quantity['imported'] - $quantity['exported'],
                'remaining_balance' => $openingBalance + $importedBalance - $exportedBalance,
            ];
        })->values();

        return [
            'rows' => $rows,
            'totals' => [
                'opening_quantity' => $rows->sum('opening_quantity'),
                'opening_balance' => $rows->sum('opening_balance'),
                'imported_quantity' => $rows->sum('imported_quantity'),
                'imported_balance' => $rows->sum('imported_balance'),
                'exported_quantity' => $rows->sum('exported_quantity'),
                'exported_balance' => $rows->sum('exported_balance'),
                'remaining_quantity' => $rows->sum('remaining_quantity'),
                'remaining_balance' => $rows->sum('remaining_balance'),
            ],
            'filters' => [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
                'product_group' => $filters['product_group'],
                'warehouse_id' => $filters['warehouse_id'],
            ],
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
            'productGroups' => ProductGroup::query()->orderBy('name')->get(['id', 'name']),
            'company' => FiscalYear::findOrFail(getActiveFiscalYear())->company,
            'generatedAt' => formatDateTime(now()),
        ];
    }

    private function validateFilters(array $rawFilters): array
    {
        $fiscalYear = FiscalYear::findOrFail(getActiveFiscalYear());
        [$fiscalStart, $fiscalEnd] = $fiscalYear->range();
        $fiscalStartDate = $fiscalStart->toDateString();
        $fiscalEndDate = $fiscalEnd->toDateString();

        $dateRule = function (string $attribute, mixed $value, $fail): void {
            try {
                jalaliInputToGregorian((string) $value, $attribute);
            } catch (ValidationException) {
                $fail(__('validation.date_format', ['attribute' => str_replace('_', ' ', $attribute), 'format' => 'Y/m/d']));
            }
        };

        Validator::make($rawFilters, [
            'start_date' => ['bail', 'nullable', 'string', $dateRule],
            'end_date' => ['bail', 'nullable', 'string', $dateRule],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('fiscal_year_id', getActiveFiscalYear())],
            'product_group' => ['nullable', 'integer', Rule::exists('product_groups', 'id')->where('fiscal_year_id', getActiveFiscalYear())],
        ])->after(function ($validator) use ($rawFilters, $fiscalStartDate, $fiscalEndDate): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $startDate = ! empty($rawFilters['start_date'])
                ? jalaliInputToGregorian($rawFilters['start_date'], 'start_date')
                : $fiscalStartDate;
            $endDate = ! empty($rawFilters['end_date'])
                ? jalaliInputToGregorian($rawFilters['end_date'], 'end_date')
                : $fiscalEndDate;

            if ($startDate < $fiscalStartDate || $startDate > $fiscalEndDate) {
                $validator->errors()->add('start_date', __('The start date must be within the active fiscal year.'));
            }

            if ($endDate < $fiscalStartDate || $endDate > $fiscalEndDate) {
                $validator->errors()->add('end_date', __('The end date must be within the active fiscal year.'));
            }

            if ($startDate > $endDate) {
                $validator->errors()->add('start_date', __('Start date cannot be greater than end date.'));
            }
        })->validate();

        $selectedStartDate = ! empty($rawFilters['start_date'])
            ? jalaliInputToGregorian($rawFilters['start_date'], 'start_date')
            : $fiscalStartDate;
        $selectedEndDate = ! empty($rawFilters['end_date'])
            ? jalaliInputToGregorian($rawFilters['end_date'], 'end_date')
            : $fiscalEndDate;

        return [
            'start_date' => $selectedStartDate,
            'end_date' => $selectedEndDate,
            'warehouse_id' => ! empty($rawFilters['warehouse_id']) ? (int) $rawFilters['warehouse_id'] : null,
            'product_group' => ! empty($rawFilters['product_group']) ? (int) $rawFilters['product_group'] : null,
        ];
    }

    private function movementQuantities(array $productIds, array $filters): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $items = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.itemable_type', Product::class)
            ->whereIn('invoice_items.itemable_id', $productIds)
            ->where('invoices.fiscal_year_id', getActiveFiscalYear())
            ->whereIn('invoices.status', $this->enumValues(InvoiceStatus::approvedOrSettled()))
            ->when($filters['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('invoices.warehouse_id', $warehouseId))
            ->when($filters['end_date'], fn (Builder $query, string $date) => $query->whereDate('invoices.date', '<=', $date))
            ->get(['invoice_items.itemable_id as product_id', 'invoice_items.quantity', 'invoices.invoice_type', 'invoices.date']);

        $imports = $this->enumValues(self::IMPORT_TYPES);
        $exports = $this->enumValues(self::EXPORT_TYPES);

        return $items->groupBy('product_id')->map(function (Collection $productItems) use ($filters, $imports, $exports): array {
            $opening = 0.0;
            $imported = 0.0;
            $exported = 0.0;

            foreach ($productItems as $item) {
                $type = (int) $item->invoice_type;
                $quantity = (float) $item->quantity;

                $date = Carbon::parse($item->date)->toDateString();

                if ($type === InvoiceType::BEGINNING_INVENTORY->value) {
                    if ($date <= $filters['start_date']) {
                        $opening += $quantity;
                    } else {
                        $imported += $quantity;
                    }

                    continue;
                }

                if ($date < $filters['start_date']) {
                    $opening += in_array($type, $imports, true) ? $quantity : -$quantity;

                    continue;
                }

                if (in_array($type, $imports, true)) {
                    $imported += $quantity;
                } elseif (in_array($type, $exports, true)) {
                    $exported += $quantity;
                }
            }

            return compact('opening', 'imported', 'exported');
        });
    }

    private function postedMovementBalances(array $subjectIds, array $filters): Collection
    {
        if ($subjectIds === []) {
            return collect();
        }

        $date = 'COALESCE(invoices.date, ancillary_costs.date)';
        $type = 'COALESCE(invoices.invoice_type, cost_invoice.invoice_type)';
        $warehouse = 'COALESCE(invoices.warehouse_id, cost_invoice.warehouse_id)';
        $approved = $this->enumValues(InvoiceStatus::approvedOrSettled());

        $movements = DB::table('transactions')
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->leftJoin('invoices', 'invoices.document_id', '=', 'documents.id')
            ->leftJoin('ancillary_costs', 'ancillary_costs.document_id', '=', 'documents.id')
            ->leftJoin('invoices as cost_invoice', 'cost_invoice.id', '=', 'ancillary_costs.invoice_id')
            ->where('documents.fiscal_year_id', getActiveFiscalYear())
            ->where(function (Builder $query) use ($approved) {
                $query->where(fn (Builder $query) => $query->where('invoices.fiscal_year_id', getActiveFiscalYear())->whereIn('invoices.status', $approved))
                    ->orWhere(fn (Builder $query) => $query->where('ancillary_costs.fiscal_year_id', getActiveFiscalYear())->where('cost_invoice.fiscal_year_id', getActiveFiscalYear())
                        ->whereIn('ancillary_costs.status', $approved)->whereIn('cost_invoice.status', $approved));
            })
            ->whereIn('transactions.subject_id', $subjectIds)
            ->when($filters['warehouse_id'], fn (Builder $query, int $id) => $query->whereRaw($warehouse.' = ?', [$id]))
            ->whereDate(DB::raw($date), '<=', $filters['end_date'])
            ->select(['transactions.subject_id', 'transactions.value'])
            ->selectRaw($date.' as movement_date, '.$type.' as invoice_type')
            ->get();

        return $movements->groupBy('subject_id')->map(function (Collection $rows) use ($filters): array {
            $totals = ['opening' => 0.0, 'imported' => 0.0, 'exported' => 0.0];
            foreach ($rows as $row) {
                $date = Carbon::parse($row->movement_date)->toDateString();
                $opening = $date < $filters['start_date'] || ((int) $row->invoice_type === InvoiceType::BEGINNING_INVENTORY->value && $date === $filters['start_date']);
                $export = in_array((int) $row->invoice_type, $this->enumValues(self::EXPORT_TYPES), true);
                $bucket = $opening ? 'opening' : ($export ? 'exported' : 'imported');
                $totals[$bucket] += ($bucket === 'exported' ? 1 : -1) * (float) $row->value;
            }

            return $totals;
        });
    }

    private function warehouseTransferMovements(array $productIds, array $filters): Collection
    {
        if (! $filters['warehouse_id'] || $productIds === []) {
            return collect();
        }

        return DB::table('warehouse_transfers')->where('fiscal_year_id', getActiveFiscalYear())
            ->whereIn('product_id', $productIds)->whereDate('transferred_at', '<=', $filters['end_date'])
            ->where(fn (Builder $query) => $query->where('from_warehouse_id', $filters['warehouse_id'])->orWhere('to_warehouse_id', $filters['warehouse_id']))
            ->get()->groupBy('product_id')->map(function (Collection $transfers) use ($filters): array {
                $totals = [];
                foreach ($transfers as $transfer) {
                    $incoming = (int) $transfer->to_warehouse_id === (int) $filters['warehouse_id'];
                    $beforeRange = Carbon::parse($transfer->transferred_at)->toDateString() < $filters['start_date'];
                    $bucket = $beforeRange ? 'opening' : ($incoming ? 'imported' : 'exported');
                    $quantity = (float) $transfer->quantity * ($beforeRange && ! $incoming ? -1 : 1);
                    $totals[$bucket.'_quantity'] = ($totals[$bucket.'_quantity'] ?? 0) + $quantity;
                    $totals[$bucket.'_balance'] = ($totals[$bucket.'_balance'] ?? 0) + $quantity * (float) $transfer->unit_cost;
                }

                return $totals;
            });
    }

    private function enumValues(array $cases): array
    {
        return array_map(fn ($case) => $case->value, $cases);
    }
}
