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
        $this->middleware('permission:document-files.view')->only('index');
        $this->middleware('permission:document-files.create')->only(['create', 'store']);
        $this->middleware('permission:document-files.edit')->only(['edit', 'update']);
        $this->middleware('permission:document-files.delete')->only('destroy');
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

        $this->service->create($validated);

        return redirect()->route('document-files.index', $validated['document_id'])->with('success', __('Document file created successfully.'));
    }

    public function edit(DocumentFile $documentFile)
    {
        $document = $documentFile->document;

        return view('documents.documentFiles.edit', compact('documentFile', 'document'));
    }

    public function update(StoreDocumentFileRequest $request, DocumentFile $documentFile)
    {
        $validated = $request->validated();

        $this->service->update($documentFile, $validated);

        return redirect()->route('document-files.index', $documentFile->document_id)->with('success', __('Document file updated successfully.'));
    }

    public function destroy(DocumentFile $documentFile)
    {
        $documentId = $documentFile->document_id;

        $this->service->delete($documentFile);

        return redirect()->route('document-files.index', $documentId)->with('success', __('Document file deleted successfully.'));
    }

    public function view(DocumentFile $documentFile)
    {
        if (! Storage::disk('public')->exists($documentFile->path)) {
            return redirect()->route('document-files.index', $documentFile->document_id)->with('error', __('No document file found.'));

        }

        return response()->file(Storage::disk('public')->path($documentFile->path));
    }

    public function download(DocumentFile $documentFile)
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($documentFile->path)) {
            return redirect()->route('document-files.index', $documentFile->document_id)->with('error', __('No document file found.'));

        }

        return response()->download($disk->path($documentFile->path), $documentFile->name);
    }
}
