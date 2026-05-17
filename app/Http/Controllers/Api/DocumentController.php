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

    public function show(int $company, int $documentId): JsonResponse
    {
        $document = Document::withoutGlobalScopes()
            ->with(['transactions', 'documentFiles'])
            ->where('company_id', getActiveCompany())
            ->findOrFail($documentId);

        return response()->json(['data' => $document]);
    }

    public function attachFile(
        AttachDocumentFileRequest $request,
        DocumentFileService $documentFileService,
        int $company,
        int $documentId
    ): JsonResponse {
        $document = Document::withoutGlobalScopes()
            ->where('company_id', getActiveCompany())
            ->findOrFail($documentId);
        $data = $request->validated();
        $data['document_id'] = $document->id;
        $data['user_id'] = $request->user()->id;

        $documentFile = $documentFileService->create($data);

        return response()->json([
            'data' => $documentFile,
        ], 201);
    }
}
