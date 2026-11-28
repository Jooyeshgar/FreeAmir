<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Mcp\Support\ContextResolver;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class ListInvoicesTool extends AccountingTool
{
    protected string $name = 'list_invoices';

    protected string $description = 'Search and list invoices in the selected authorized fiscal year by invoice number, title, ID, type, status, customer, or date range. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['invoices.index'], ['invoices.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to query invoices from.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'query' => $schema->string()->max(255)->description('Optional search text matching invoice number, title, or exact numeric ID.'),
            'type' => $schema->string()->description('Optional invoice type filter: buy, sell, return_buy, return_sell, void, or beginning_inventory.')->enum(['buy', 'sell', 'return_buy', 'return_sell', 'void', 'beginning_inventory']),
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
        $limit = isset($arguments['limit']) ? max(1, min(50, (int) $arguments['limit'])) : 15;
        $page = isset($arguments['page']) ? max(1, (int) $arguments['page']) : 1;

        $canReadCustomers = ContextResolver::authContext()?->can('customers.index') ?? false;

        $invoiceType = isset($arguments['type']) ? InvoiceType::tryFromName((string) $arguments['type']) : null;
        if (isset($arguments['type']) && $invoiceType === null) {
            return $this->error(__('Invalid invoice type.'));
        }

        $invoiceStatus = isset($arguments['status']) ? InvoiceStatus::tryFromName((string) $arguments['status']) : null;
        if (isset($arguments['status']) && $invoiceStatus === null) {
            return $this->error(__('Invalid invoice status.'));
        }

        $paginator = InvoiceService::searchQuery([
            'q' => $arguments['query'] ?? '',
            'invoice_type' => $invoiceType,
            'status' => $invoiceStatus,
            'customer_id' => $arguments['customer_id'] ?? null,
            'date_from' => $arguments['date_from'] ?? null,
            'date_to' => $arguments['date_to'] ?? null,
            'with_customer' => $canReadCustomers,
        ])
            ->where('fiscal_year_id', $fiscalYear->id)
            ->paginate($limit, ['*'], 'page', $page);

        $invoices = collect($paginator->items())
            ->map(fn (Invoice $invoice): array => InvoiceService::formatSummary($invoice, $currency, $canReadCustomers))
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
}
