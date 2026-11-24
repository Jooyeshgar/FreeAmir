<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Extensions\InvoiceExtensionRegistry;
use App\Http\Requests\StoreInvoiceRequest;
use App\Mcp\Support\ContextResolver;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class CreateInvoiceTool extends AccountingTool
{
    protected string $name = 'create_invoice';

    protected string $description = 'Create an unapproved purchase or sales draft in the selected authorized open fiscal year. Prices and discounts use its company currency.';

    protected array $permissionGroups = [['invoices.store']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        $item = $schema->object([
            'item_type' => $schema->string()->enum(['product', 'service'])->required(),
            'item_id' => $schema->integer()->min(1)->required(),
            'quantity' => $schema->number()->min(1)->required(),
            'unit' => $schema->number()->min(0)->description('Unit price in the selected fiscal year company currency.')->required(),
            'discount' => $schema->number()->min(0)->description('Total line discount in the selected fiscal year company currency, not a percentage.'),
            'vat' => $schema->number()->min(0)->max(100)->description('VAT percentage from 0 to 100.'),
            'description' => $schema->string()->max(500),
        ])->withoutAdditionalProperties();

        $properties = [
            'fiscal_year_id' => $schema->integer()->min(1)->required(),
            'invoice_type' => $schema->string()->enum(['buy', 'sell'])->required(),
            'customer_id' => $schema->integer()->min(1)->required(),
            'warehouse_id' => $schema->integer()->min(1)->description('Optional warehouse ID. Defaults to the first warehouse in the selected fiscal year.'),
            'number' => $schema->integer()->min(1)->description('Optional invoice number. Defaults to the next number for this invoice type in the selected fiscal year.'),
            'external_reference_id' => $schema->string()->max(100)->description('Optional external reference ID to prevent duplicate invoices for external orders (recommended format: "<source>:<order_code>", e.g. "fnirsi:FN-180625").'),
            'date' => $schema->string()->description('Gregorian date in YYYY-MM-DD format, regardless of locale. Defaults to today if it falls within the selected fiscal year.'),
            'title' => $schema->string()->max(255),
            'description' => $schema->string(),
            'subtraction' => $schema->number()->min(0)->description('Invoice deduction in the selected fiscal year company currency.'),
            'items' => $schema->array()->min(1)->max(500)->items($item)->required(),
        ];

        $registry = app(InvoiceExtensionRegistry::class);
        $extensions = $registry->extensions();
        if ($extensions !== []) {
            $extensionProperties = [];
            foreach (array_keys($extensions) as $key) {
                $extensionProperties[$key] = $schema->union(['object', 'array'])->description($registry->mcpDescription($key));
            }
            $properties['extensions'] = $schema->object($extensionProperties)
                ->withoutAdditionalProperties()
                ->description('Optional registered invoice extension payloads.');
        }

        return $properties;
    }

    protected function rules(): array
    {
        $rules = [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'invoice_type' => ['required', 'string', 'in:buy,sell'],
            'customer_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'external_reference_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'subtraction' => ['sometimes', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['required', 'array:item_type,item_id,quantity,unit,discount,vat,description'],
            'items.*.item_type' => ['required', 'string', 'in:product,service'],
            'items.*.item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.unit' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['sometimes', 'numeric', 'min:0'],
            'items.*.vat' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'items.*.description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];

        if (app(InvoiceExtensionRegistry::class)->extensions() !== []) {
            $rules['extensions'] = ['sometimes', 'array'];
            $rules['extensions.*'] = ['array'];
        }

        return $rules;
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $date = $arguments['date'] ?? now()->toDateString();
        [$start, $end] = $fiscalYear->range();
        if ($date < $start->toDateString() || $date > $end->toDateString()) {
            throw ValidationException::withMessages(['date' => __('Provide an invoice date within the selected fiscal year.')]);
        }

        $yearId = (int) $arguments['fiscal_year_id'];
        $externalReferenceId = isset($arguments['external_reference_id']) ? trim((string) $arguments['external_reference_id']) : null;
        if ($externalReferenceId === '') {
            $externalReferenceId = null;
        }

        if ($externalReferenceId !== null) {
            $existing = Invoice::query()
                ->where('fiscal_year_id', $yearId)
                ->where('external_reference_id', $externalReferenceId)
                ->where('invoice_type', '!=', InvoiceType::VOID->value)
                ->where('status', '!=', InvoiceStatus::REJECTED->value)
                ->first();

            if ($existing) {
                return $this->error(json_encode([
                    'message' => __('An invoice with this external reference ID already exists in the selected fiscal year.'),
                    'existing_invoice' => [
                        'id' => (int) $existing->id,
                        'number' => $existing->number,
                        'external_reference_id' => $existing->external_reference_id,
                        'invoice_type' => $existing->invoice_type?->valueName(),
                        'status' => $existing->status?->valueName(),
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }
        }
        $customer = Customer::query()->find($arguments['customer_id']);
        if (! $customer || (int) $customer->fiscal_year_id !== $yearId) {
            throw ValidationException::withMessages(['customer_id' => __('The selected customer is invalid.')]);
        }

        $warehouseId = $arguments['warehouse_id'] ?? Warehouse::where('fiscal_year_id', $yearId)->orderBy('id')->value('id');
        if (! $warehouseId) {
            throw ValidationException::withMessages(['warehouse_id' => __('No warehouse found for this fiscal year.')]);
        }
        $warehouse = Warehouse::query()->find($warehouseId);
        if (! $warehouse || (int) $warehouse->fiscal_year_id !== $yearId) {
            throw ValidationException::withMessages(['warehouse_id' => __('The selected warehouse is invalid.')]);
        }

        $invoiceNumber = $arguments['number'] ?? (
            (Invoice::where('fiscal_year_id', $yearId)
                ->where('invoice_type', InvoiceType::fromName($arguments['invoice_type']))
                ->max('number') ?? 0) + 1
        );

        $items = collect($arguments['items']);
        $products = Product::query()->where('fiscal_year_id', $yearId)
            ->whereIn('id', $items->where('item_type', 'product')->pluck('item_id'))->pluck('id')->flip();
        $services = Service::query()->where('fiscal_year_id', $yearId)
            ->whereIn('id', $items->where('item_type', 'service')->pluck('item_id'))->pluck('id')->flip();
        $transactions = [];
        foreach ($arguments['items'] as $index => $item) {
            $availableItems = $item['item_type'] === 'product' ? $products : $services;
            if (! $availableItems->has($item['item_id'])) {
                throw ValidationException::withMessages(["items.{$index}.item_id" => __('The selected item is invalid.')]);
            }
            // The web form normalizer expects decimal strings rather than exponent notation.
            $transactions[] = [
                'item_id' => $item['item_type'].'-'.$item['item_id'],
                'quantity' => sprintf('%.18F', $item['quantity']),
                'unit' => sprintf('%.18F', $item['unit']),
                'discount' => sprintf('%.18F', $item['discount'] ?? 0),
                'vat' => sprintf('%.18F', $item['vat'] ?? 0),
                'desc' => $item['description'] ?? null,
                'total' => sprintf('%.18F', (float) $item['quantity'] * (float) $item['unit']),
            ];
        }

        $request = $this->validateFormRequest(StoreInvoiceRequest::class, [
            'title' => $arguments['title'] ?? null,
            'description' => $arguments['description'] ?? null,
            'date' => convertToJalali($date, true),
            'invoice_type' => $arguments['invoice_type'],
            'customer_id' => $arguments['customer_id'],
            'warehouse_id' => $warehouseId,
            'invoice_number' => $invoiceNumber,
            'external_reference_id' => $externalReferenceId,
            'document_number' => floor(Document::max('number') ?? 0) + 1,
            'subtraction' => sprintf('%.18F', $arguments['subtraction'] ?? 0),
            'transactions' => $transactions,
        ]);
        $validated = $request->validated();

        $extensionPayloads = [];
        if (isset($arguments['extensions']) && $arguments['extensions'] !== []) {
            $extensionRequest = HttpRequest::create('/', 'POST', ['extensions' => $arguments['extensions']]);
            $extensionPayloads = app(InvoiceExtensionRegistry::class)->validateRequest($extensionRequest);
        }

        $extensionPersister = $extensionPayloads === []
            ? null
            : static function (Invoice $invoice) use ($extensionPayloads): void {
                app(InvoiceExtensionRegistry::class)->persist($invoice, $extensionPayloads);
            };

        $result = InvoiceService::createInvoice(
            ContextResolver::authContext()->user,
            InvoiceService::extractInvoiceData($validated),
            InvoiceService::mapTransactionsToItems($validated['transactions']),
            false,
            $extensionPersister,
        );

        /** @var Invoice $invoice */
        $invoice = $result['invoice']->fresh(['items']);

        return $this->success([
            'id' => $invoice->id,
            'fiscal_year_id' => $invoice->fiscal_year_id,
            'number' => $invoice->number,
            'external_reference_id' => $invoice->external_reference_id,
            'invoice_type' => $invoice->invoice_type->valueName(),
            'status' => $invoice->status->valueName(),
            'date' => $invoice->date->format('Y-m-d'),
            'customer_id' => $invoice->customer_id,
            'warehouse_id' => $invoice->warehouse_id,
            'amount' => (float) $invoice->amount,
            'currency' => (string) ($fiscalYear->company?->currency ?: 'Rial'),
            'vat' => (float) $invoice->vat,
            'item_count' => $invoice->items->count(),
            'approved' => false,
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => $item->itemable_type === Product::class ? 'product' : 'service',
                'item_id' => $item->itemable_id,
                'quantity' => (float) $item->quantity,
                'unit' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'vat' => (float) $item->vat,
                'amount' => (float) $item->amount,
            ])->all(),
            'message' => __('Invoice created successfully.'),
        ]);
    }
}
