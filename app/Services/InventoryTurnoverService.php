<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
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
        $openingBalances = $this->invoiceMovementBalances(
            $subjectIds,
            [InvoiceType::BEGINNING_INVENTORY],
            null,
            $filters['start_date'],
            $filters['warehouse_id'],
            excludeThrough: true
        );
        $importedBalances = $this->invoiceMovementBalances($subjectIds, self::IMPORT_TYPES, $filters['start_date'], $filters['end_date'], $filters['warehouse_id']);
        $exportedBalances = $this->invoiceMovementBalances($subjectIds, self::EXPORT_TYPES, $filters['start_date'], $filters['end_date'], $filters['warehouse_id']);

        $rows = $products->map(function (Product $product) use ($quantities, $openingBalances, $importedBalances, $exportedBalances): array {
            $quantity = $quantities[$product->id] ?? ['opening' => 0.0, 'imported' => 0.0, 'exported' => 0.0];
            $openingBalance = abs((float) ($openingBalances[$product->inventory_subject_id] ?? 0));
            $importedBalance = abs((float) ($importedBalances[$product->inventory_subject_id] ?? 0));
            $exportedBalance = abs((float) ($exportedBalances[$product->inventory_subject_id] ?? 0));

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
            'company' => Company::find(getActiveCompany()),
            'generatedAt' => formatDateTime(now()),
        ];
    }

    private function validateFilters(array $rawFilters): array
    {
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
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'product_group' => ['nullable', 'integer', Rule::exists('product_groups', 'id')->where('company_id', getActiveCompany())],
        ])->after(function ($validator) use ($rawFilters): void {
            if (empty($rawFilters['start_date']) || empty($rawFilters['end_date']) || $validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            if (jalaliInputToGregorian($rawFilters['start_date'], 'start_date') > jalaliInputToGregorian($rawFilters['end_date'], 'end_date')) {
                $validator->errors()->add('start_date', __('Start date cannot be greater than end date.'));
            }
        })->validate();

        $company = Company::query()->findOrFail(getActiveCompany());
        $start_date = Carbon::parse(jalali_to_gregorian($company->fiscal_year, 1, 1, '/'))->addDays(4)->format('Y-m-d');
        $end_date = Carbon::parse(jalali_to_gregorian($company->fiscal_year, jdate('m'), jdate('d'), '/'))->format('Y-m-d');

        return [
            'start_date' => ! empty($rawFilters['start_date']) ? jalaliInputToGregorian($rawFilters['start_date'], 'start_date') : $start_date,
            'end_date' => ! empty($rawFilters['end_date']) ? jalaliInputToGregorian($rawFilters['end_date'], 'end_date') : $end_date,
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
            ->where('invoices.company_id', getActiveCompany())
            ->whereIn('invoices.status', $this->enumValues(InvoiceStatus::approvedOrSettled()))
            ->when($filters['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('invoices.warehouse_id', $warehouseId))
            ->when($filters['end_date'], fn (Builder $query, string $date) => $query->where('invoices.date', '<=', $date))
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

                if ($type === InvoiceType::BEGINNING_INVENTORY->value) {
                    if ($item->date < $filters['start_date']) {
                        $opening += $quantity;
                    }

                    continue;
                }

                if ($item->date < $filters['start_date']) {
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

    private function invoiceMovementBalances(array $subjectIds, array $types, ?string $from, ?string $through, ?int $warehouseId = null, bool $excludeThrough = false): Collection
    {
        if ($subjectIds === []) {
            return collect();
        }

        return DB::table('transactions')
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->join('invoices', 'invoices.document_id', '=', 'documents.id')
            ->where('invoices.company_id', getActiveCompany())
            ->whereIn('invoices.status', $this->enumValues(InvoiceStatus::approvedOrSettled()))
            ->whereIn('invoices.invoice_type', $this->enumValues($types))
            ->whereIn('transactions.subject_id', $subjectIds)
            ->when($warehouseId, fn (Builder $query, int $id) => $query->where('invoices.warehouse_id', $id))
            ->when($from, fn (Builder $query, string $date) => $query->where('invoices.date', '>=', $date))
            ->when($through, fn (Builder $query, string $date) => $query->where('invoices.date', $excludeThrough ? '<' : '<=', $date))
            ->groupBy('transactions.subject_id')
            ->selectRaw('transactions.subject_id, SUM(transactions.value) as balance')
            ->pluck('balance', 'transactions.subject_id');
    }

    private function enumValues(array $cases): array
    {
        return array_map(fn ($case) => $case->value, $cases);
    }
}
