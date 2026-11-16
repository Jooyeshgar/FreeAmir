<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceType;
use App\Http\Requests\StoreMcpInvoiceRequest;
use App\Mcp\Support\ContextResolver;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class CreateInvoiceTool extends AccountingTool
{
    protected string $name = 'create_invoice';

    protected string $description = 'ایجاد پیش‌فاکتور خرید یا فروش با اقلام و قیمت‌ها؛ فاکتور تأیید نمی‌شود.';

    protected array $permissionGroups = [['invoices.store']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        $item = $schema->object([
            'item_type' => $schema->string()->enum(['product', 'service'])->required(),
            'item_id' => $schema->integer()->min(1)->required(),
            'quantity' => $schema->number()->min(1)->required(),
            'unit' => $schema->number()->min(0)->required(),
            'discount' => $schema->number()->min(0),
            'vat' => $schema->number()->min(0)->max(100),
            'description' => $schema->string()->max(500),
        ])->withoutAdditionalProperties();

        return [
            'fiscal_year_id' => $schema->integer()->min(1)->required(),
            'invoice_type' => $schema->string()->enum(['buy', 'sell'])->required(),
            'customer_id' => $schema->integer()->min(1)->required(),
            'warehouse_id' => $schema->integer()->min(1)->description('شناسه انبار. اختیاری؛ در صورت عدم ارسال از انبار اصلی سال مالی استفاده می‌شود.'),
            'number' => $schema->integer()->min(1)->description('شماره فاکتور. اختیاری؛ در صورت عدم ارسال، به صورت خودکار شماره بعدی انتخاب می‌شود.'),
            'date' => $schema->string()->description('Gregorian date in YYYY-MM-DD format (اختیاری؛ پیش‌فرض: امروز).'),
            'title' => $schema->string()->max(255),
            'description' => $schema->string(),
            'subtraction' => $schema->number()->min(0),
            'items' => $schema->array()->min(1)->max(500)->items($item)->required(),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'invoice_type' => ['required', 'string', 'in:buy,sell'],
            'customer_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'number' => ['sometimes', 'nullable', 'integer', 'min:1'],
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
    }

    protected function report(array $arguments): Response
    {
        $yearId = (int) $arguments['fiscal_year_id'];
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
        $date = $arguments['date'] ?? date('Y-m-d');

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
            $transactions[] = [
                'item_id' => (int) $item['item_id'],
                'item_type' => $item['item_type'],
                'quantity' => (float) $item['quantity'],
                'unit' => (float) $item['unit'],
                'discount' => (float) ($item['discount'] ?? 0),
                'vat' => (float) ($item['vat'] ?? 0),
                'desc' => $item['description'] ?? null,
                'total' => (float) $item['quantity'] * (float) $item['unit'],
            ];
        }

        $request = $this->validateFormRequest(StoreMcpInvoiceRequest::class, [
            'title' => $arguments['title'] ?? null,
            'description' => $arguments['description'] ?? null,
            'date' => $date,
            'invoice_type' => $arguments['invoice_type'],
            'customer_id' => $arguments['customer_id'],
            'warehouse_id' => $warehouseId,
            'invoice_number' => $invoiceNumber,
            'subtractions' => $arguments['subtraction'] ?? 0,
            'transactions' => $transactions,
        ]);
        $validated = $request->validated();

        $result = InvoiceService::createInvoice(
            ContextResolver::authContext()->user,
            InvoiceService::extractInvoiceData($validated),
            InvoiceService::mapTransactionsToItems($validated['transactions']),
            false,
        );

        /** @var Invoice $invoice */
        $invoice = $result['invoice']->fresh(['items']);

        return $this->success([
            'id' => $invoice->id,
            'fiscal_year_id' => $invoice->fiscal_year_id,
            'number' => $invoice->number,
            'invoice_type' => $invoice->invoice_type->valueName(),
            'status' => $invoice->status->valueName(),
            'date' => $invoice->date->format('Y-m-d'),
            'customer_id' => $invoice->customer_id,
            'warehouse_id' => $invoice->warehouse_id,
            'amount' => (float) $invoice->amount,
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
