<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentFileRequest;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\DocumentFileService;
use Illuminate\Support\Facades\Storage;

class DocumentFileController extends Controller
{
    public function __construct(
        private readonly DocumentFileService $service
    ) {
        $this->middleware('permission:documents.create')->only(['create', 'store']);
        $this->middleware('permission:documents.edit')->only(['edit', 'update']);
        $this->middleware('permission:documents.delete')->only('destroy');
    }

    public function index(Document $document)
    {
        $documentFiles = DocumentFile::where('document_id', $document->id)->latest()->paginate(25);

        return view('documents.documentFiles.index', compact('documentFiles', 'document'));
    }

    public function create(Document $document)
    {
        $documentFile = new DocumentFile;

        return view('documents.documentFiles.create', compact('documentFile', 'document'));
    }

    public function store(StoreDocumentFileRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] ??= $request->user()->id;

        $this->service->create($validated);

        return redirect()->route('document-files.index', $validated['document_id'])->with('success', __('Document file created successfully.'));
    }

    public function edit(Document $document, DocumentFile $documentFile)
    {
        $document = $documentFile->document;

        return view('documents.documentFiles.edit', compact('documentFile', 'document'));
    }

    public function update(StoreDocumentFileRequest $request, Document $document, DocumentFile $documentFile)
    {
        $validated = $request->validated();
        $validated['user_id'] ??= $request->user()->id;

        $this->service->update($documentFile, $validated);

        return redirect()->route('document-files.index', $documentFile->document_id)->with('success', __('Document file updated successfully.'));
    }

    public function destroy(Document $document, DocumentFile $documentFile)
    {
        $documentId = $documentFile->document_id;

        $this->service->delete($documentFile);

        return redirect()->route('document-files.index', $documentId)->with('success', __('Document file deleted successfully.'));
    }

    public function view(Document $document, DocumentFile $documentFile)
    {
        $disk = Storage::disk('public');
        $path = $this->service->resolvePath($documentFile);

        if (! $disk->exists($path)) {
            return redirect()->route('document-files.index', $document)->with('error', __('No document file found.'));
        }

        return response()->file($disk->path($path));
    }

    public function download(Document $document, DocumentFile $documentFile)
    {
        $disk = Storage::disk('public');
        $path = $this->service->resolvePath($documentFile);

        if (! $disk->exists($path)) {
            return redirect()->route('document-files.index', $documentFile->document_id)->with('error', __('No document file found.'));
        }

        return response()->download($disk->path($path), $documentFile->name ?? basename($path));
    }
}
