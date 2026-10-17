<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentFileRequest;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\DocumentFileService;
use App\Services\FiscalYearMutationGuard;
use Illuminate\Support\Facades\Storage;

class DocumentFileController extends Controller
{
    public function __construct(private readonly DocumentFileService $service, private readonly FiscalYearMutationGuard $fiscalYearMutationGuard) {}

    public function create(Document $document)
    {
        $this->fiscalYearMutationGuard->ensureOpen();

        $documentFile = new DocumentFile;

        return view('documents.documentFiles.create', compact('documentFile', 'document'));
    }

    public function store(StoreDocumentFileRequest $request)
    {
        $this->fiscalYearMutationGuard->ensureOpen();
        $validated = $request->validated();
        $validated['user_id'] ??= $request->user()->id;

        $this->service->create($validated);

        return redirect()->route('documents.show', $validated['document_id'])->with('success', __('Document file created successfully.'));
    }

    public function edit(Document $document, DocumentFile $documentFile)
    {
        $this->fiscalYearMutationGuard->ensureOpen();

        $document = $documentFile->document;

        return view('documents.documentFiles.edit', compact('documentFile', 'document'));
    }

    public function update(StoreDocumentFileRequest $request, Document $document, DocumentFile $documentFile)
    {
        $this->fiscalYearMutationGuard->ensureOpen();

        $validated = $request->validated();
        $validated['user_id'] ??= $request->user()->id;

        $this->service->update($documentFile, $validated);

        return redirect()->route('documents.show', $documentFile->document_id)->with('success', __('Document file updated successfully.'));
    }

    public function destroy(Document $document, DocumentFile $documentFile)
    {
        $this->fiscalYearMutationGuard->ensureOpen();

        $documentId = $documentFile->document_id;

        $this->service->delete($documentFile);

        return redirect()->route('documents.show', $documentId)->with('success', __('Document file deleted successfully.'));
    }

    public function view(Document $document, DocumentFile $documentFile)
    {
        $disk = Storage::disk('public');
        $path = $this->service->resolvePath($documentFile);

        if (! $disk->exists($path)) {
            return redirect()->route('documents.show', $document)->with('error', __('No document file found.'));
        }

        return response()->file($disk->path($path));
    }

    public function download(Document $document, DocumentFile $documentFile)
    {
        $disk = Storage::disk('public');
        $path = $this->service->resolvePath($documentFile);

        if (! $disk->exists($path)) {
            return redirect()->route('documents.show', $documentFile->document_id)->with('error', __('No document file found.'));
        }

        return response()->download($disk->path($path), $documentFile->name ?? basename($path));
    }
}
