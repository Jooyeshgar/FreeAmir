<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function show(int $company, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->with([
                'customer',
                'warehouse',
                'document',
                'items.itemable',
                'returnedInvoice',
            ])
            ->findOrFail($id);

        $items = $invoice->items->map(function ($item) {
            $itemable = $item->itemable;

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_discount' => $item->unit_discount,
                'vat' => $item->vat,
                'quantity_at' => $item->quantity_at,
                'cog_after' => $item->cog_after,
                'amount' => $item->amount,
                'description' => $item->description,
                'itemable_type' => class_basename($item->itemable_type),
                'itemable_id' => $item->itemable_id,
                'itemable' => $itemable ? [
                    'id' => $itemable->id,
                    'code' => $itemable->code ?? null,
                    'name' => $itemable->name ?? null,
                ] : null,
            ];
        });

        $data = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'date' => $invoice->date?->format('Y-m-d'),
            'title' => $invoice->title,
            'invoice_type' => $invoice->invoice_type?->valueName(),
            'invoice_type_label' => $invoice->invoice_type?->label(),
            'status' => $invoice->status?->valueName(),
            'status_label' => $invoice->status?->label(),
            'amount' => $invoice->amount,
            'vat' => $invoice->vat,
            'subtraction' => $invoice->subtraction,
            'taxID' => $invoice->taxID,
            'description' => $invoice->description,
            'ship_date' => $invoice->ship_date?->format('Y-m-d'),
            'ship_via' => $invoice->ship_via,
            'customer_id' => $invoice->customer_id,
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name ?? null,
            ] : null,
            'warehouse_id' => $invoice->warehouse_id,
            'warehouse' => $invoice->warehouse ? [
                'id' => $invoice->warehouse->id,
                'name' => $invoice->warehouse->name ?? null,
            ] : null,
            'document_id' => $invoice->document_id,
            'document' => $invoice->document ? [
                'id' => $invoice->document->id,
                'number' => $invoice->document->number ?? null,
                'date' => optional($invoice->document->date)->format('Y-m-d'),
            ] : null,
            'returned_invoice_id' => $invoice->returned_invoice_id,
            'returned_invoice' => $invoice->returnedInvoice ? [
                'id' => $invoice->returnedInvoice->id,
                'number' => $invoice->returnedInvoice->number,
                'date' => $invoice->returnedInvoice->date?->format('Y-m-d'),
                'title' => $invoice->returnedInvoice->title,
            ] : null,
            'creator_id' => $invoice->creator_id,
            'approver_id' => $invoice->approver_id,
            'items' => $items,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ];

        return response()->json([
            'data' => $data,
        ]);
    }

    public function index(Request $request, $company): JsonResponse
    {
        $request->validate([
            'invoice_type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'start_date' => ['nullable', 'string'],
            'end_date' => ['nullable', 'string'],
            'number' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $invoiceType = InvoiceType::tryFromName($request->input('invoice_type'));
        $status = InvoiceStatus::tryFromName($request->input('status'));

        $query = Invoice::query()
            ->with(['customer:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('number');

        if ($invoiceType !== null) {
            $query->where('invoice_type', $invoiceType);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($request->filled('number')) {
            $query->where('number', $request->input('number'));
        }

        if ($request->filled('start_date')) {
            $query->where('date', '>=', $this->normalizeDateFilter($request->input('start_date')));
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $this->normalizeDateFilter($request->input('end_date')));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($customer) use ($q) {
                        $customer->where('name', 'like', "%{$q}%");
                    });
            });
        }

        $perPage = $request->integer('per_page', 15);
        $invoices = $query->paginate($perPage);

        $data = $invoices->map(function (Invoice $invoice) {
            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->date?->format('Y-m-d'),
                'title' => $invoice->title,
                'invoice_type' => $invoice->invoice_type?->valueName(),
                'invoice_type_label' => $invoice->invoice_type?->label(),
                'status' => $invoice->status?->valueName(),
                'status_label' => $invoice->status?->label(),
                'amount' => $invoice->amount,
                'vat' => $invoice->vat,
                'subtraction' => $invoice->subtraction,
                'customer_id' => $invoice->customer_id,
                'customer' => $invoice->customer ? [
                    'id' => $invoice->customer->id,
                    'name' => $invoice->customer->name,
                ] : null,
                'warehouse_id' => $invoice->warehouse_id,
                'document_id' => $invoice->document_id,
                'returned_invoice_id' => $invoice->returned_invoice_id,
                'description' => $invoice->description,
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $invoices->currentPage(),
            'total' => $invoices->total(),
            'per_page' => $invoices->perPage(),
            'last_page' => $invoices->lastPage(),
        ]);
    }

    public function store(Request $request, int $company): JsonResponse
    {
        $validated = $request->validate([
            'invoice_type' => ['required', 'string', Rule::in(['buy', 'sell', 'return_buy', 'return_sell'])],
            'date' => ['required', 'string'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('fiscal_year_id', getScopedFiscalYear())],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('fiscal_year_id', getScopedFiscalYear())],
            'invoice_number' => ['required', 'integer'],
            'document_number' => ['required', 'numeric'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subtraction' => ['nullable', 'numeric', 'min:0'],
            'returned_invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'approve' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'string', Rule::in(['product', 'service'])],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $normalizedDate = toEnglish(str_replace('-', '/', trim($validated['date'])));

        if (! preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalizedDate, $dateParts)) {
            throw ValidationException::withMessages([
                'date' => __('Invalid date format. Example: 1405/08/10 (Jalali) or 2026-11-01 (Gregorian)'),
            ]);
        }

        [$dateYear, $dateMonth, $dateDay] = [(int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[3]];

        try {
            if ($dateYear < 1700) {
                if (! jcheckdate($dateMonth, $dateDay, $dateYear)) {
                    throw new \InvalidArgumentException('invalid jalali date');
                }
                $date = Carbon::createFromFormat('Y/n/j', jalali_to_gregorian_date($normalizedDate))->format('Y-m-d');
            } else {
                if (! checkdate($dateMonth, $dateDay, $dateYear)) {
                    throw new \InvalidArgumentException('invalid gregorian date');
                }
                $date = sprintf('%04d-%02d-%02d', $dateYear, $dateMonth, $dateDay);
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'date' => __('Invalid date. Example: 1405/08/10 (Jalali) or 2026-11-01 (Gregorian)'),
            ]);
        }
        $invoiceTypeEnum = InvoiceType::fromName($validated['invoice_type']);

        if (in_array($validated['invoice_type'], ['return_buy', 'return_sell'], true)) {
            $returned = Invoice::query()
                ->find($validated['returned_invoice_id'] ?? null);

            if (! $returned) {
                throw ValidationException::withMessages([
                    'returned_invoice_id' => __('For a return invoice, the original invoice ID is required and must be valid.'),
                ]);
            }

            $expectedType = $validated['invoice_type'] === 'return_buy' ? InvoiceType::BUY : InvoiceType::SELL;
            if ($returned->invoice_type !== $expectedType) {
                throw ValidationException::withMessages([
                    'returned_invoice_id' => __('The original invoice type does not match the return invoice type.'),
                ]);
            }

            if (Carbon::parse($date)->lt($returned->date)) {
                throw ValidationException::withMessages([
                    'date' => __('The return invoice date cannot be before the original invoice.'),
                ]);
            }

            $previousReturns = Invoice::where('returned_invoice_id', $returned->id)->with('items')->get();

            foreach ($validated['items'] as $index => $item) {
                $modelClass = $item['item_type'] === 'product' ? Product::class : Service::class;

                $original = $returned->items()
                    ->where('itemable_type', $modelClass)
                    ->where('itemable_id', $item['item_id'])
                    ->first();

                if (! $original) {
                    throw ValidationException::withMessages([
                        "items.$index.item_id" => __('This product or service does not exist in the original invoice.'),
                    ]);
                }

                $alreadyReturned = $previousReturns->sum(fn ($inv) => $inv->items
                    ->where('itemable_type', $modelClass)
                    ->where('itemable_id', $item['item_id'])
                    ->sum('quantity'));

                if ($original->quantity < $item['quantity'] + $alreadyReturned) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => __('The returned quantity (including previous returns) exceeds the original invoice quantity (:quantity).', [
                            'quantity' => $original->quantity,
                        ]),
                    ]);
                }
            }

            if ($validated['invoice_type'] === 'return_sell') {
                $submitted = collect($validated['items'])->mapWithKeys(
                    fn ($item) => [$item['item_type'].':'.$item['item_id'] => (float) $item['quantity']]
                );

                $originals = $returned->items->mapWithKeys(function ($item) {
                    $type = $item->itemable_type === Product::class ? 'product' : 'service';

                    return [$type.':'.$item->itemable_id => (float) $item->quantity];
                });

                $isFullReturn = $originals->count() === $submitted->count()
                    && $originals->every(fn ($qty, $key) => $submitted->get($key) === $qty);

                if ($isFullReturn) {
                    throw ValidationException::withMessages([
                        'items' => __('Returning all items with the original quantities is equivalent to voiding the sales invoice; reduce the quantity of at least one item.'),
                    ]);
                }
            }
        }

        $numberExists = Invoice::query()
            ->where('number', $validated['invoice_number'])
            ->where('invoice_type', $invoiceTypeEnum)
            ->exists();

        if ($numberExists) {
            throw ValidationException::withMessages([
                'invoice_number' => __('The invoice number has already been taken.'),
            ]);
        }

        $transactions = collect($validated['items'])->map(function (array $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $unitDiscount = (float) ($item['unit_discount'] ?? 0);
            $vatPercent = (float) $item['vat'];

            $lineAfterDiscount = ($quantity * $unitPrice) - $unitDiscount;
            $vatAmount = $lineAfterDiscount * ($vatPercent / 100);
            $total = $lineAfterDiscount + $vatAmount;

            return [
                'item_id' => $item['item_id'],
                'item_type' => $item['item_type'],
                'quantity' => $quantity,
                'unit' => $unitPrice,
                'unit_discount' => $unitDiscount,
                'vat' => $vatPercent,
                'desc' => $item['description'] ?? null,
                'total' => $total,
            ];
        })->values()->all();

        $invoiceData = [
            'title' => $validated['title'] ?? null,
            'date' => $date,
            'invoice_type' => $invoiceTypeEnum,
            'customer_id' => $validated['customer_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'returned_invoice_id' => $validated['returned_invoice_id'] ?? null,
            'document_number' => $validated['document_number'],
            'number' => $validated['invoice_number'],
            'subtraction' => $validated['subtraction'] ?? 0,
            'description' => $validated['description'] ?? null,
        ];

        $items = InvoiceService::mapTransactionsToItems($transactions, false);

        $approved = (bool) ($validated['approve'] ?? false);

        if ($approved && ! $request->user()->can('invoices.approve')) {
            throw ValidationException::withMessages([
                'approve' => __('You do not have permission to approve invoices.'),
            ]);
        }

        $result = InvoiceService::createInvoice(
            $request->user(),
            $invoiceData,
            $items,
            $approved
        );

        $invoice = $result['invoice']->load(['customer', 'items.itemable', 'warehouse']);

        return response()->json([
            'data' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->date?->format('Y-m-d'),
                'title' => $invoice->title,
                'invoice_type' => $invoice->invoice_type?->valueName(),
                'status' => $invoice->status?->valueName(),
                'amount' => $invoice->amount,
                'vat' => $invoice->vat,
                'subtraction' => $invoice->subtraction,
                'customer_id' => $invoice->customer_id,
                'warehouse_id' => $invoice->warehouse_id,
                'document_id' => $invoice->document_id,
                'returned_invoice_id' => $invoice->returned_invoice_id,
                'description' => $invoice->description,
                'items_count' => $invoice->items->count(),
                'created_at' => $invoice->created_at,
            ],
            'meta' => [
                'approved' => $invoice->status?->valueName() === 'approved',
                'document_id' => $result['document']?->id,
                'next_action' => $invoice->status?->valueName() === 'ready_to_approve'
                    ? "POST /api/companies/{$company}/invoices/{$invoice->id}/change-status/approved"
                    : null,
            ],
        ], 201);
    }

    public function changeStatus(Request $request, int $company, int $invoice, string $status): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($invoice);

        $allowed = $invoice->invoice_type === InvoiceType::SELL
            ? ['approved', 'unapproved', 'ready_to_approve', 'rejected']
            : ['approved', 'unapproved'];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('Invalid status. Allowed statuses: :statuses', ['statuses' => implode(', ', $allowed)]),
            ]);
        }

        if (in_array($status, ['approved', 'unapproved'], true) && ! $request->user()->can('invoices.approve')) {
            throw ValidationException::withMessages([
                'status' => __('You do not have permission to approve or unapprove invoices.'),
            ]);
        }

        if ($invoice->status->isPartiallyPaid() || $invoice->status->isPaid()) {
            throw ValidationException::withMessages([
                'status' => __('Remove the recorded payments before changing the invoice status.'),
            ]);
        }

        auth()->setUser($request->user());

        $invoiceService = app(InvoiceService::class);
        $decision = $invoiceService->getChangeStatusDecision($invoice, $status);

        if ($decision->hasErrors()) {
            $error = $decision->messages->first(fn ($m) => $m->type === 'error');

            throw ValidationException::withMessages([
                'status' => $error?->text ?? __('The status change is not possible.'),
            ]);
        }

        $invoiceService->changeInvoiceStatus($invoice, $status);

        if ($status === 'approved') {
            $invoice->approver_id = $request->user()->id;
            $invoice->save();
        }

        if ($status === 'unapproved') {
            $invoice->approver_id = null;
            $invoice->save();
        }

        $invoice->refresh();

        return response()->json([
            'data' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status?->valueName(),
                'document_id' => $invoice->document_id,
                'approver_id' => $invoice->approver_id,
            ],
        ]);
    }

    private function normalizeDateFilter(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $normalized = toEnglish(str_replace('-', '/', trim($date)));

        if (! preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $parts)) {
            return $date;
        }

        [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];

        if ($year < 1700) {
            return jalali_to_gregorian_date($normalized);
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
