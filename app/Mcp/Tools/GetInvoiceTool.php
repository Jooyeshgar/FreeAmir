<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Models\FiscalYear;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetInvoiceTool extends AccountingTool
{
    protected string $name = 'get_invoice';

    protected string $description = 'Retrieve full details for a single invoice by invoice ID or exact invoice number within the selected authorized fiscal year, including line items, warehouse, and accounting document reference. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['invoices.show'], ['invoices.index']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to inspect the invoice in.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'invoice_id' => $schema->integer()->min(1)->description('Invoice ID (provide invoice_id or number).'),
            'number' => $schema->string()->max(50)->description('Exact invoice number (provide invoice_id or number).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'year' => ['sometimes', 'integer', 'min:1300', 'max:1500'],
            'invoice_id' => ['required_without:arguments.number', 'integer', 'min:1'],
            'number' => ['required_without:arguments.invoice_id', 'string', 'max:50'],
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

        if (isset($arguments['invoice_id'])) {
            $query->where('id', (int) $arguments['invoice_id']);
        }

        if (isset($arguments['number'])) {
            $query->where('number', trim((string) $arguments['number']));
        }

        $invoice = $query->first();

        if (! $invoice) {
            return $this->error(__('Invoice not found in the selected fiscal year.'));
        }

        $items = $invoice->items->map(function ($item): array {
            $itemable = $item->itemable;

            return [
                'id' => (int) $item->id,
                'item_type' => $item->itemable_type ? strtolower(class_basename($item->itemable_type)) : null,
                'item_id' => $item->itemable_id !== null ? (int) $item->itemable_id : null,
                'item_code' => $itemable?->code ?? null,
                'item_name' => $itemable?->name ?? null,
                'quantity' => (float) ($item->quantity ?? 0),
                'unit_price' => (float) ($item->unit_price ?? 0),
                'discount' => (float) ($item->discount ?? 0),
                'vat' => (float) ($item->vat ?? 0),
                'amount' => (float) ($item->amount ?? 0),
                'description' => $item->description,
            ];
        })->values()->all();

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
