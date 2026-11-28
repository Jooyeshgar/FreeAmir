<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceType;
use App\Mcp\Support\ContextResolver;
use App\Models\FiscalYear;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetInvoiceTool extends AccountingTool
{
    protected string $name = 'get_invoice';

    protected string $description = 'Retrieve full details for a single invoice by invoice ID or exact invoice number (optionally an exact external reference ID) within the selected authorized fiscal year, including line items, warehouse, and accounting document reference. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['invoices.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to inspect the invoice in.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'invoice_id' => $schema->integer()->min(1)->description('Invoice ID (provide invoice_id or number).'),
            'number' => $schema->string()->max(50)->description('Exact invoice number (provide invoice_id or number).'),
            'external_reference_id' => $schema->string()->max(100)->description('Exact external reference ID (alternative to invoice_id or number).'),
            'invoice_type' => $schema->string()->description('Optional invoice type to disambiguate when querying by number: buy, sell, return_buy, return_sell, void, or beginning_inventory.')->enum(['buy', 'sell', 'return_buy', 'return_sell', 'void', 'beginning_inventory']),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'year' => ['sometimes', 'integer', 'min:1300', 'max:1500'],
            'invoice_id' => ['required_without_all:arguments.number,arguments.external_reference_id', 'integer', 'min:1'],
            'number' => ['required_without_all:arguments.invoice_id,arguments.external_reference_id', 'string', 'max:50'],
            'external_reference_id' => ['required_without_all:arguments.invoice_id,arguments.number', 'string', 'max:100'],
            'invoice_type' => ['sometimes', 'string'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');
        $canReadCustomers = ContextResolver::authContext()?->can('customers.index') ?? false;

        $relations = [
            'warehouse:id,name',
            'document:id,number,date',
            'items.itemable',
            'returnedInvoice:id,number',
        ];
        if ($canReadCustomers) {
            $relations[] = 'customer:id,name,phone,mobile';
        }

        $query = Invoice::query()
            ->with($relations)
            ->where('fiscal_year_id', $fiscalYear->id);

        $invoiceType = isset($arguments['invoice_type']) ? InvoiceType::tryFromName((string) $arguments['invoice_type']) : null;
        if (isset($arguments['invoice_type']) && $invoiceType === null) {
            return $this->error(__('Invalid invoice type.'));
        }

        if (isset($arguments['invoice_id'])) {
            $query->where('id', (int) $arguments['invoice_id']);
        }

        if (isset($arguments['number'])) {
            $query->where('number', trim((string) $arguments['number']));
        }

        if (isset($arguments['external_reference_id'])) {
            $query->where('external_reference_id', trim((string) $arguments['external_reference_id']));
        }

        if ($invoiceType !== null) {
            $query->where('invoice_type', $invoiceType->value);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            return $this->error(__('Invoice not found in the selected fiscal year.'));
        }

        if ($matches->count() > 1) {
            return $this->error(json_encode([
                'message' => __('Multiple invoices matched this number. Specify invoice_id or invoice_type.'),
                'matching_invoices' => $matches->map(fn (Invoice $match): array => [
                    'id' => (int) $match->id,
                    'number' => $match->number !== null ? (string) $match->number : null,
                    'external_reference_id' => $match->external_reference_id,
                    'invoice_type' => $match->invoice_type?->valueName(),
                    'date' => $match->date?->format('Y-m-d'),
                    'amount' => ListInvoicesTool::decimalAmount($match->amount),
                ])->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $invoice = $matches->first();

        $items = ListInvoicesTool::formatItems($invoice);

        $shipDate = $invoice->ship_date?->format('Y-m-d');
        $summary = ListInvoicesTool::formatSummary($invoice, $currency, $canReadCustomers);

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'invoice' => array_merge($summary, [
                'warehouse_id' => $invoice->warehouse_id !== null ? (int) $invoice->warehouse_id : null,
                'warehouse_name' => $invoice->warehouse?->name,
                'document_id' => $invoice->document_id !== null ? (int) $invoice->document_id : null,
                'document_number' => $invoice->document?->number,
                'returned_invoice_id' => $invoice->returned_invoice_id !== null ? (int) $invoice->returned_invoice_id : null,
                'returned_invoice_number' => $invoice->returnedInvoice?->number,
                'ship_date' => $shipDate,
                'ship_date_jalali' => $shipDate !== null ? (gregorian_to_jalali_date($shipDate, '/', '-') ?: null) : null,
                'ship_via' => $invoice->ship_via,
                'tax_id' => $invoice->taxID,
                'description' => $invoice->description,
                'items' => $items,
            ]),
            'human_summary' => __('Invoice :number (ID :id, :type): total :amount with :count items.', [
                'number' => $invoice->number ?: '-',
                'id' => $invoice->id,
                'type' => $summary['type_label'] ?? '-',
                'amount' => $summary['formatted_amount'],
                'count' => count($items),
            ]),
        ]);
    }
}
