<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\DocumentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $query = Document::orderByDesc('date');

        if (request()->has('number') && request('number')) {
            $query->where('number', convertToFloat(request('number')));
        }

        if (request()->has('date') && request('date')) {
            $query->where('date', convertToGregorian(request('date')));
        }

        // Search by document title or transaction description
        if (request()->has('text') && request('text')) {
            $searchText = request('text');
            $query->where(function ($q) use ($searchText) {
                $q->where('title', 'like', $searchText)
                    ->orWhereHas('transactions', function ($subQ) use ($searchText) {
                        $subQ->where('desc', 'like', $searchText);
                    });
            });
        }

        $documents = $query->paginate(10);

        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        $subjects = $this->formatSubjectsForTree($this->loadRootSubjects());

        $transactions = old('transactions')
                    ? self::prepareTransactions(old('transactions'))
                    : self::prepareTransactions([new Transaction]);

        $total = count($transactions);
        $document = new Document;
        $previousDocumentNumber = floor(Document::orderBy('number')->first()?->number) ?? 0;

        return view('documents.create', compact('document', 'previousDocumentNumber', 'subjects', 'transactions', 'total'));
    }

    public function store(StoreTransactionRequest $request)
    {
        $transactions = [];
        foreach ($request->input('transactions') as $transactionData) {
            $transactionData = (object) $transactionData;
            $transactions[] = [
                'subject_id' => $transactionData->subject_id,
                'value' => $transactionData->credit - $transactionData->debit,
                'desc' => $transactionData->desc,
            ];
        }

        DocumentService::createDocument(
            Auth::user(),
            [
                'title' => $request->title,
                'number' => $request->number,
                'date' => $request->date,
                'user_id' => Auth::id(),
            ],
            $transactions
        );

        return redirect()->route('documents.index')->with('success', __('Document created successfully.'));
    }

    public function show(Document $document)
    {
        return view('documents.show', compact('document'));
    }

    public function print(Document $document)
    {
        return view('documents.print', compact('document'));
    }

    public function edit($id)
    {
        $document = Document::find($id);
        if ($document) {
            if ($document->documentable) {
                return redirect()->route('documents.index')->with('error', __('Cannot edit this document because it is linked to').' '.__(class_basename($document->documentable_type)).'.');
            }

            $transactions = old('transactions')
                    ? self::prepareTransactions(old('transactions'))
                    : self::prepareTransactions($document->transactions);

            $total = -1;

            $documentSubjects = $document->transactions->map(fn ($t) => $t->subject);

            $subjects = $this->formatSubjectsForTree($this->loadRootSubjects($documentSubjects));

            $previousDocumentNumber = Document::where('number', '<', $document->number)->orderBy('number', 'desc')->first()->number ?? 0;

            return view('documents.edit', compact('previousDocumentNumber', 'document', 'subjects', 'transactions', 'total'));
        } else {
            return redirect()->route('documents.index')->with('error', 'Document not found.');
        }
    }

    /**
     * Update the specified document and its transactions.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(StoreTransactionRequest $request, $id)
    {
        $document = Document::findOrFail($id);

        DocumentService::updateDocument($document, $request->toArray());

        DocumentService::updateDocumentTransactions($document->id, $request->input('transactions'));

        return redirect()->route('documents.index')->with('success', __('Document updated successfully.'));
    }

    public function destroy(int $documentId)
    {
        DocumentService::deleteDocument($documentId);

        return redirect()->route('documents.index')->with('success', __('Document deleted successfully.'));
    }

    /**
     * Duplicate the specified document with all its transactions.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function duplicate($id)
    {
        $originalDocument = Document::with('transactions')->findOrFail($id);

        // Get the next document number
        $nextDocumentNumber = Document::orderBy('id', 'desc')->first()->number + 1;

        // Prepare transactions data
        $transactions = [];
        foreach ($originalDocument->transactions as $transaction) {
            $transactions[] = [
                'subject_id' => $transaction->subject_id,
                'value' => $transaction->value,
                'desc' => $transaction->desc,
            ];
        }

        // Create the duplicated document
        $newDocument = DocumentService::createDocument(
            Auth::user(),
            [
                'title' => $originalDocument->title.' ('.__('Copy').')',
                'number' => $nextDocumentNumber,
                'date' => $originalDocument->date,
                'user_id' => Auth::id(),
            ],
            $transactions
        );

        return redirect()->route('documents.edit', $newDocument->id)
            ->with('success', __('Document duplicated successfully.'));
    }

    private function subjectExistsInTree(Subject $node, int $targetId): bool
    {
        if ($node->id === $targetId) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->subjectExistsInTree($child, $targetId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepares a collection of transaction data for further display.
     *
     * @param  array|\Illuminate\Support\Collection  $transactions
     * @return \Illuminate\Support\Collection
     */
    private static function prepareTransactions($transactions)
    {
        $transactions = collect($transactions);

        return $transactions->map(function ($t, $i) {
            $isModel = is_object($t);

            return [
                'id' => $i + 1,
                'transaction_id' => $isModel ? $t->id : ($t['transaction_id'] ?? null),
                'subject_id' => $isModel ? $t->subject_id : ($t['subject_id'] ?? ''),
                'subject' => $isModel ? ($t->subject?->name ?? '') : ($t['subject'] ?? ''),
                'code' => $isModel ? ($t->subject?->code ?? '') : ($t['code'] ?? ''),
                'desc' => $isModel ? ($t->desc ?? '') : ($t['desc'] ?? ''),
                'credit' => $isModel ? ($t->credit ?? 0) : ($t['credit'] ?? 0),
                'debit' => $isModel ? ($t->debit ?? 0) : ($t['debit'] ?? 0),
            ];
        });
    }

    public function subjectSearch(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'parent_id' => 'nullable|integer|exists:subjects,id',
            'selected_id' => 'nullable|integer|exists:subjects,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $validated['limit'] ?? 25;

        if (! empty($validated['selected_id'])) {
            return response()->json(
                $this->buildSelectedPathPayload((int) $validated['selected_id'], $limit)
            );
        }

        $subjectsQuery = Subject::query()->withCount('children')->orderBy('code');

        if (! empty($validated['parent_id'])) {
            $subjectsQuery->where('parent_id', $validated['parent_id']);
        } elseif (! empty($validated['q'])) {
            $searchTerm = $validated['q'];
            $subjectsQuery->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('code', 'like', '%'.$searchTerm.'%');
            });
        } else {
            $subjectsQuery->whereNull('parent_id');
        }

        $subjects = $subjectsQuery->limit($limit)->get();

        $results = $subjects->map(function ($subject) use ($validated) {
            $node = $this->formatSubjectNode($subject);

            if (! empty($validated['q']) && empty($validated['parent_id'])) {
                $node['ancestors'] = $this->buildAncestorTrail($subject);
            }

            return $node;
        });

        return response()->json(['results' => $results]);
    }

    private function loadRootSubjects(?Collection $documentSubjects = null): Collection
    {
        $roots = Subject::whereIsRoot()->withCount('children')->orderBy('code')->limit(50)->get();

        if ($documentSubjects && $documentSubjects->isNotEmpty()) {
            $existingRootIds = $roots->pluck('id');
            $neededRootIds = $documentSubjects->map(fn ($subject) => $subject?->getRoot()?->id)->filter()->unique()
                ->diff($existingRootIds);

            if ($neededRootIds->isNotEmpty()) {
                $missingRoots = Subject::whereIn('id', $neededRootIds)->withCount('children')->orderBy('code')->get();

                $roots = $roots->concat($missingRoots)->unique('id')->sortBy('code')->values();
            }
        }

        return $roots;
    }

    private function formatSubjectsForTree(Collection $subjects): array
    {
        return $subjects->map(fn ($subject) => $this->formatSubjectNode($subject))->values()->all();
    }

    private function formatSubjectNode(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'parent_id' => $subject->parent_id,
            'has_children' => ($subject->children_count ?? 0) > 0,
        ];
    }

    private function buildAncestorTrail(Subject $subject): array
    {
        return $subject->ancestors()->map(fn ($ancestor) => $this->formatSubjectNode($ancestor))->values()->all();
    }

    private function buildSelectedPathPayload(int $selectedId, int $limit = 25): array
    {
        $subject = Subject::with('parent')->findOrFail($selectedId);

        $path = $subject->ancestors()->push($subject);

        $pathWithIndex = $path->values();

        $prefetch = $pathWithIndex->map(function (Subject $node, int $index) use ($pathWithIndex, $limit) {
            $preferredChild = $pathWithIndex[$index + 1] ?? null;

            $childrenQuery = Subject::where('parent_id', $node->id)->withCount('children');

            if ($preferredChild) {
                $childrenQuery->orderByRaw('id = ? desc', [$preferredChild->id]);
            }

            $children = $childrenQuery->orderBy('code')->limit($limit)->get();

            return [
                'parent_id' => $node->id,
                'children' => $children->map(fn ($child) => $this->formatSubjectNode($child))->values()->all(),
            ];
        })->values()->all();

        return [
            'path' => $path->map(fn ($node) => $this->formatSubjectNode($node))->values()->all(),
            'prefetch' => $prefetch,
        ];
    }
}
