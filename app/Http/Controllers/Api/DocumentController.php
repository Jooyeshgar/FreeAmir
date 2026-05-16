<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttachDocumentFileRequest;
use App\Http\Requests\Api\StoreDocumentRequest;
use App\Models\Document;
use App\Services\DocumentFileService;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = DocumentService::createDocument(
            $request->user(),
            $request->safe()->only(['title', 'number', 'date']),
            $request->validated('transactions')
        );

        return response()->json([
            'data' => $document->load('transactions'),
        ], 201);
    }

    public function show(int $documentId): JsonResponse
    {
        $document = Document::with(['transactions', 'documentFiles'])->findOrFail($documentId);

        return response()->json(['data' => $document]);
    }

    public function attachFile(
        AttachDocumentFileRequest $request,
        DocumentFileService $documentFileService,
        int $documentId
    ): JsonResponse {
        $document = Document::findOrFail($documentId);
        $data = $request->validated();
        $data['document_id'] = $document->id;
        $data['user_id'] = $request->user()->id;

        $documentFileService->create($data);
        $document->load('documentFiles');

        return response()->json([
            'data' => $document->documentFiles()->latest()->first(),
        ], 201);
    }
}
