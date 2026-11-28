<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Mcp\Support\ContextResolver;
use App\Models\FiscalYear;
use App\Models\Invoice;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class ListInvoicesTool extends AccountingTool
{
    protected string $name = 'list_invoices';

    protected string $description = 'Search and list invoices in the selected authorized fiscal year by invoice number, title, ID, external reference ID, type, status, customer, or date range. Optionally include line items. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['invoices.index'], ['invoices.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to query invoices from.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'query' => $schema->string()->max(255)->description('Optional search text matching invoice number, title, external reference ID, or exact numeric ID.'),
            'type' => $schema->string()->description('Optional invoice type filter: buy, sell, return_buy, return_sell, void, or beginning_inventory.')->enum(['buy', 'sell', 'return_buy', 'return_sell', 'void', 'beginning_inventory']),
            'external_reference_id' => $schema->string()->max(100)->description('Optional exact external reference ID filter (e.g. "fnirsi:FN-180625").'),
            'include_items' => $schema->boolean()->description('Include line items for each invoice (default: false).'),
            'status' => $schema->string()->description('Optional invoice status filter (e.g. pending, pre_invoice, approved, rejected, partially_paid, paid; numeric status values are also accepted).'),
            'customer_id' => $schema->integer()->min(1)->description('Optional customer ID filter.'),
            'date_from' => $schema->string()->description('Optional Gregorian start date (YYYY-MM-DD).'),
            'date_to' => $schema->string()->description('Optional Gregorian end date (YYYY-MM-DD).'),
            'page' => $schema->integer()->min(1)->description('Page number (default: 1).'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum number of invoices per page (default: 15, max: 50).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'year' => ['sometimes', 'integer', 'min:1300', 'max:1500'],
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'string'],
            'external_reference_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'include_items' => ['sometimes', 'nullable', 'boolean'],
            'status' => ['sometimes', 'string'],
            'customer_id' => ['sometimes', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');
        $search = isset($arguments['query']) ? trim((string) $arguments['query']) : '';
        $limit = isset($arguments['limit']) ? max(1, min(50, (int) $arguments['limit'])) : 15;
        $page = isset($arguments['page']) ? max(1, (int) $arguments['page']) : 1;

        $invoiceType = isset($arguments['type']) ? InvoiceType::tryFromName((string) $arguments['type']) : null;
        if (isset($arguments['type']) && $invoiceType === null) {
            return $this->error(__('Invalid invoice type.'));
        }

        $invoiceStatus = isset($arguments['status']) ? InvoiceStatus::tryFromName((string) $arguments['status']) : null;
        if (isset($arguments['status']) && $invoiceStatus === null) {
            return $this->error(__('Invalid invoice status.'));
        }

        $canReadCustomers = ContextResolver::authContext()?->can('customers.index') ?? false;

        $query = Invoice::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($canReadCustomers) {
            $query->with('customer:id,name');
        }

        $includeItems = (bool) ($arguments['include_items'] ?? false);
        if ($includeItems) {
            $query->with('items.itemable');
        }

        $externalReferenceId = isset($arguments['external_reference_id']) ? trim((string) $arguments['external_reference_id']) : '';
        if ($externalReferenceId !== '') {
            $query->where('external_reference_id', $externalReferenceId);
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($search, $escaped) {
                $q->where('number', 'like', "%{$escaped}%")
                    ->orWhere('title', 'like', "%{$escaped}%")
                    ->orWhere('external_reference_id', 'like', "%{$escaped}%");

                if (ctype_digit($search) && (int) $search > 0) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if ($invoiceType !== null) {
            $query->where('invoice_type', $invoiceType->value);
        }

        if ($invoiceStatus !== null) {
            $query->where('status', $invoiceStatus->value);
        }

        if (isset($arguments['customer_id'])) {
            $query->where('customer_id', (int) $arguments['customer_id']);
        }

        if (isset($arguments['date_from'])) {
            $query->whereDate('date', '>=', $arguments['date_from']);
        }

        if (isset($arguments['date_to'])) {
            $query->whereDate('date', '<=', $arguments['date_to']);
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        $invoices = collect($paginator->items())
            ->map(function (Invoice $invoice) use ($currency, $canReadCustomers, $includeItems): array {
                $row = self::formatSummary($invoice, $currency, $canReadCustomers);
                if ($includeItems) {
                    $row['items'] = self::formatItems($invoice);
                }

                return $row;
            })
            ->all();

        $humanSummary = $paginator->total() === 0
            ? __('No invoices found in fiscal year :year.', ['year' => $fiscalYear->year])
            : __('Found :total matching invoices (showing page :page of :last_page).', [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ]);

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'invoices' => $invoices,
            'human_summary' => $humanSummary,
        ]);
    }

    public static function formatSummary(Invoice $invoice, string $currency, bool $includeCustomerName): array
    {
        $invoiceType = InvoiceType::tryFrom((int) ($invoice->invoice_type?->value ?? $invoice->invoice_type));
        $status = $invoice->status instanceof InvoiceStatus
            ? $invoice->status
            : InvoiceStatus::tryFrom((int) $invoice->status);

        $rawDate = $invoice->date?->format('Y-m-d');
        $jalaliDate = $rawDate !== null ? (gregorian_to_jalali_date($rawDate, '/', '-') ?: null) : null;
        $amount = self::decimalAmount($invoice->amount);
        [$whole, $fraction] = explode('.', $amount);
        $whole = preg_replace('/\B(?=(?:[0-9]{3})+\z)/', ',', $whole);
        $fraction = rtrim($fraction, '0');
        $formattedAmount = $fraction === '' ? $whole : $whole.'.'.$fraction;

        return [
            'id' => (int) $invoice->id,
            'number' => $invoice->number !== null ? (string) $invoice->number : null,
            'external_reference_id' => $invoice->external_reference_id,
            'title' => $invoice->title,
            'type' => $invoiceType?->valueName(),
            'type_label' => $invoiceType?->label(),
            'status' => $status?->valueName(),
            'status_label' => $status?->label(),
            'customer_id' => $invoice->customer_id !== null ? (int) $invoice->customer_id : null,
            'customer_name' => $includeCustomerName ? ($invoice->customer?->name ?? null) : null,
            'date' => $rawDate,
            'jalali_date' => $jalaliDate,
            'amount' => $amount,
            'formatted_amount' => $formattedAmount.' '.$currency,
            'vat' => self::decimalAmount($invoice->vat),
            'subtraction' => self::decimalAmount($invoice->subtraction),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function formatItems(Invoice $invoice): array
    {
        return $invoice->items->map(function ($item): array {
            $itemable = $item->itemable;

            return [
                'id' => (int) $item->id,
                'item_type' => $item->itemable_type ? strtolower(class_basename($item->itemable_type)) : null,
                'item_id' => $item->itemable_id !== null ? (int) $item->itemable_id : null,
                'item_code' => $itemable?->code ?? null,
                'item_name' => $itemable?->name ?? null,
                'item_external_sku' => $itemable?->external_sku ?? null,
                'quantity' => (float) ($item->quantity ?? 0),
                'unit_price' => self::decimalAmount($item->unit_price),
                'discount' => self::decimalAmount($item->discount),
                'vat' => self::decimalAmount($item->vat),
                'amount' => self::decimalAmount($item->amount),
                'description' => $item->description,
            ];
        })->values()->all();
    }

    public static function decimalAmount(string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2, RoundingMode::HALF_UP);
    }
}
