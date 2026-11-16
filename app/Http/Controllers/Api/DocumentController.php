<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttachDocumentFileRequest;
use App\Http\Requests\Api\StoreDocumentRequest;
use App\Models\CommercialLedgerExport;
use App\Models\Document;
use App\Services\DocumentFileService;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $ledger = CommercialLedgerExport::query()
            ->where('from_date', '<=', $request->validated('date'))
            ->where('to_date', '>=', $request->validated('date'))
            ->latest('id')
            ->first();

        if ($ledger && ! $request->boolean('ledger_confirmation')) {
            return response()->json([
                'message' => __('A commercial ledger has already been issued for this date (:from to :to). If you are sure, send ledger_confirmation=true.', [
                    'from' => $ledger->from_date->format('Y-m-d'),
                    'to' => $ledger->to_date->format('Y-m-d'),
                ]),
                'needs_confirmation' => true,
            ], 409);
        }

        $document = DocumentService::createDocument(
            $request->user(),
            $request->safe()->only(['title', 'number', 'date']),
            $request->validated('transactions')
        );

        return response()->json([
            'data' => $this->formatDocument($document->load('transactions.subject')),
        ], 201);
    }

    public function show(int $company, int $documentId): JsonResponse
    {
        $document = Document::query()
            ->with(['transactions.subject', 'documentFiles'])
            ->findOrFail($documentId);

        return response()->json(['data' => $this->formatDocument($document, true)]);
    }

    public function attachFile(
        AttachDocumentFileRequest $request,
        DocumentFileService $documentFileService,
        int $company,
        int $documentId
    ): JsonResponse {
        $document = Document::query()->findOrFail($documentId);
        $data = $request->validated();
        $data['document_id'] = $document->id;
        $data['user_id'] = $request->user()->id;

        $documentFile = $documentFileService->create($data);

        return response()->json([
            'data' => $documentFile,
        ], 201);
    }

    public function changeStatus(Request $request, int $company, int $documentId, string $status): JsonResponse
    {
        if (! in_array($status, ['approved', 'unapproved'], true)) {
            throw ValidationException::withMessages([
                'status' => __('Invalid status. Allowed statuses: approved, unapproved'),
            ]);
        }

        $document = Document::query()
            ->with('transactions')
            ->findOrFail($documentId);

        if ($document->documentable_id) {
            throw ValidationException::withMessages([
                'status' => __('Cannot change status of this document because it created automatically.'),
            ]);
        }

        $isApproved = $document->approved_at !== null;

        if (($status === 'approved' && $isApproved) || ($status === 'unapproved' && ! $isApproved)) {
            throw ValidationException::withMessages([
                'status' => __('The document is already in this status.'),
            ]);
        }

        DocumentService::changeDocumentStatus($document, $request->user(), $status);
        $document->refresh();

        return response()->json([
            'data' => [
                'id' => $document->id,
                'number' => $document->number,
                'date' => $document->date,
                'status' => $document->approved_at ? 'approved' : 'unapproved',
                'approved_at' => $document->approved_at,
            ],
        ]);
    }

    public function index(Request $request, int $company): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['nullable', 'numeric'],
            'text' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,approved,unapproved'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Document::query()
            ->withCount('transactions')
            ->withSum(['transactions as total_credit' => fn ($q) => $q->where('value', '>', 0)], 'value')
            ->orderByDesc('date')
            ->orderByDesc('number');

        if (isset($validated['number'])) {
            $query->where('number', $validated['number']);
        }

        if (($validated['status'] ?? 'all') === 'approved') {
            $query->whereNotNull('approved_at');
        } elseif (($validated['status'] ?? 'all') === 'unapproved') {
            $query->whereNull('approved_at');
        }

        if (! empty($validated['from_date'])) {
            $query->where('date', '>=', $validated['from_date']);
        }

        if (! empty($validated['to_date'])) {
            $query->where('date', '<=', $validated['to_date']);
        }

        if (! empty($validated['text'])) {
            $text = trim($validated['text']);

            $query->where(function ($q) use ($text) {
                $q->where('title', 'like', "%{$text}%")
                    ->orWhereHas('transactions', fn ($t) => $t->where('desc', 'like', "%{$text}%"));
            });
        }

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($d) => [
                'id' => $d->id,
                'number' => $d->number,
                'date' => $d->date?->format('Y-m-d'),
                'title' => $d->title,
                'status' => $d->approved_at ? 'approved' : 'unapproved',
                'is_automatic' => $d->documentable_id !== null,
                'transactions_count' => (int) $d->transactions_count,
                'total' => (float) ($d->total_credit ?? 0),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function formatDocument(Document $document, bool $withFiles = false): array
    {
        $transactions = $document->transactions->map(function ($t) {
            $value = (float) $t->value;

            return [
                'id' => $t->id,
                'subject' => $t->subject ? [
                    'id' => $t->subject->id,
                    'code' => $t->subject->code,
                    'name' => $t->subject->name,
                    'type' => $t->subject->type?->valueName(),
                ] : null,
                'desc' => $t->desc,
                'value' => $value,
                'debit' => $value < 0 ? abs($value) : 0.0,
                'credit' => $value > 0 ? $value : 0.0,
            ];
        })->values();

        $totalDebit = (float) $transactions->sum('debit');
        $totalCredit = (float) $transactions->sum('credit');

        $data = [
            'id' => $document->id,
            'number' => $document->number,
            'date' => $document->date?->format('Y-m-d'),
            'title' => $document->title,
            'status' => $document->approved_at ? 'approved' : 'unapproved',
            'approved_at' => $document->approved_at?->format('Y-m-d'),
            'is_automatic' => $document->documentable_id !== null,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit - $totalCredit, 2) == 0,
            'transactions' => $transactions,
        ];

        if ($withFiles) {
            $data['files'] = $document->documentFiles->map(fn ($f) => [
                'id' => $f->id,
                'title' => $f->title,
                'name' => $f->name,
                'created_at' => $f->created_at,
            ])->values();
        }

        return $data;
    }
}
