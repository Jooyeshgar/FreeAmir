<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        if ($request->boolean('approve') && ! $request->user()->can('invoices.approve')) {
            throw ValidationException::withMessages([
                'approve' => __('You do not have permission to approve invoices.'),
            ]);
        }

        $validated = $request->validated();

        $result = InvoiceService::createInvoice(
            $request->user(),
            InvoiceService::extractInvoiceData($validated),
            InvoiceService::mapTransactionsToItems($validated['transactions']),
            $request->boolean('approve')
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
                    ? 'POST /api/companies/{fiscal_year}/invoices/{id}/approve'
                    : null,
            ],
        ], 201);
    }

    public function approve(Request $request, int $company, int $invoice): JsonResponse
    {
        return $this->applyStatusChange($request, $invoice, 'approved');
    }

    public function unapprove(Request $request, int $company, int $invoice): JsonResponse
    {
        return $this->applyStatusChange($request, $invoice, 'unapproved');
    }

    private function applyStatusChange(Request $request, int $invoiceId, string $status): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);

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
