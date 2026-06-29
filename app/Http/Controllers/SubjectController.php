<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SubjectController extends Controller
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function index(Request $request)
    {
        $importedDefaultName = (bool) $request->input('name_is_default', false);
        $currentParent = null;

        if ($importedDefaultName) {
            $prefixes = [
                __('Kol :code', ['code' => '']),
                __('Moein :code', ['code' => '']),
                __('Tafsili :code', ['code' => '']),
                explode(':n', __('Level :n :code'))[0],
            ];
            $subjects = Subject::where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    if ($prefix !== '') {
                        $q->orWhere('name', 'like', "{$prefix}%");
                    }
                }
            })->with('subjectable')->orderBy('code')->get();
        } elseif ($request->has('parent_id')) {
            $currentParent = Subject::find($request->input('parent_id'));
            $subjects = $currentParent->children()->with('subjectable')->orderBy('code')->get();
        } else {
            $subjects = Subject::whereIsRoot()->with('subjectable')->orderBy('code')->get();
        }

        return view('subjects.index', compact('subjects', 'currentParent', 'importedDefaultName'));
    }

    public function create(Request $request)
    {

        if ($request->has('parent_id')) {
            $parentSubject = Subject::find($request->get('parent_id'));
        } else {
            $parentSubject = null;
        }

        return view('subjects.create', compact('parentSubject'));
    }

    public function store(StoreSubjectRequest $request)
    {
        $validatedData = $request->getValidatedData();

        $subject = $this->subjectService->createSubject($validatedData);

        $redirectUrl = route('subjects.index');
        if ($subject->parent_id) {
            $redirectUrl .= '?parent_id='.$subject->parent_id;
        }

        return redirect($redirectUrl)->with('success', __('Subject with code :code created successfully.', ['code' => $subject->formattedCode()]));
    }

    public function edit(Subject $subject)
    {
        $parentSubject = $subject->parent;
        $subjects = Subject::orderBy('code')->get(['id', 'name', 'code', 'parent_id']);
        $subjects = $this->subjectService->buildSubjectTreeFromCollection($subjects);

        return view('subjects.edit', compact('subject', 'parentSubject', 'subjects'));
    }

    public function update(StoreSubjectRequest $request, Subject $subject)
    {
        $validatedData = $request->getValidatedData();

        try {
            $updatedSubject = $this->subjectService->editSubject($subject, $validatedData);

            $redirectUrl = route('subjects.index');
            if ($updatedSubject->parent_id) {
                $redirectUrl .= '?parent_id='.$updatedSubject->parent_id;
            }

            return redirect($redirectUrl)->with('success', __('Subject updated successfully.'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()
                ->withErrors(['code' => $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Subject $subject)
    {

        try {
            $subject->delete();

            return redirect()->back()->with('success', __('Subject deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('errors', $e->getMessage());
        }
    }

    private function collectWithRelations(Collection $subjects): Collection
    {
        $result = $subjects->keyBy('id');

        $downIds = $subjects->pluck('id')->unique()->values(); // children
        $upIds = $subjects->pluck('parent_id')->filter()->unique()->values(); // parents

        while ($downIds->isNotEmpty() || $upIds->isNotEmpty()) {
            $children = collect();
            if ($downIds->isNotEmpty()) {
                $children = Subject::query()->select(['id', 'name', 'code', 'parent_id'])->whereIn('parent_id', $downIds)->get()
                    ->reject(fn ($s) => $result->has($s->id));
            }

            $parents = collect();
            if ($upIds->isNotEmpty()) {
                $parents = Subject::query()->select(['id', 'name', 'code', 'parent_id'])->whereIn('id', $upIds)->get()
                    ->reject(fn ($s) => $result->has($s->id));
            }

            if ($children->isEmpty() && $parents->isEmpty()) {
                break;
            }

            // merge results
            foreach ($children as $child) {
                $result->put($child->id, $child);
            }

            foreach ($parents as $parent) {
                $result->put($parent->id, $parent);
            }

            // next iteration IDs
            $downIds = $children->pluck('id')->values();
            $upIds = $parents->pluck('parent_id')->filter()->reject(fn ($id) => $result->has($id))->unique()->values();
        }

        return $result->values();
    }

    /**
     * Search for a subject by name.
     * Format the result as a tree with parents and children for each matched subject.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:0|max:100',
        ]);

        $q = $validated['q'];

        $matched = Subject::query()->select(['id', 'name', 'code', 'parent_id'])->where('name', 'like', "%{$q}%")
            ->orderBy('code')->limit(25)->get();

        if ($matched->isEmpty()) {
            return response()->json([]);
        }

        $subjects = $this->collectWithRelations($matched);

        return response()->json($this->subjectService->buildSubjectTreeFromCollection($subjects));
    }

    /**
     * Search for a subject by code.
     * This is used in document form when user enters a code.
     * It tries to find an exact match for the code and returns the subject with its parents and children if found.
     */
    public function searchCode(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:20',
        ]);

        $q = preg_replace('/[^0-9]/', '', $validated['q']); // Normalize code by removing '/' characters

        $matched = Subject::query()->select(['id', 'name', 'code', 'parent_id'])->where('code', $q)->first();

        if (! $matched) {
            return response()->json([]);
        }

        $subjects = $this->collectWithRelations(collect([$matched]));

        return response()->json($this->subjectService->buildSubjectTreeFromCollection($subjects));
    }

    public function transferSubjectForm()
    {
        $subjects = Subject::orderBy('code')->get(['id', 'name', 'code', 'parent_id']);
        $subjects = $this->subjectService->buildSubjectTreeFromCollection($subjects);

        return view('subjects.transfer', compact('subjects'));
    }

    public function transferSubject(Request $request)
    {
        $isCreateNew = $request->boolean('create_new_subject');

        $rules = [
            'source_subject_id' => 'required|integer|exists:subjects,id',
        ];

        if ($isCreateNew) {
            $rules['parent_destination_subject_id'] = 'required|integer|exists:subjects,id';
        } else {
            $rules['destination_subject_id'] = 'required|integer|exists:subjects,id|different:source_subject_id';
        }

        $request->validate($rules);

        $sourceSubject = Subject::find($request->source_subject_id);

        $removeSource = $request->boolean('remove_source_subject');

        try {
            $transferSubjectable = $request->boolean('transfer_subjectable');

            if ($isCreateNew) {
                $result = $this->subjectService->transferSubjectToNewUnderParent($sourceSubject, Subject::find($request->parent_destination_subject_id), $transferSubjectable, $removeSource);
            } else {
                $result = $this->subjectService->transferSubject($sourceSubject, Subject::find($request->destination_subject_id), $transferSubjectable, $removeSource);
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        $route = $request->input('submit_action') === 'create_new' ? 'subjects.transfer-form' : 'subjects.index';

        $response = redirect()->route($route)->with('success', __(':count transactions transferred successfully. Total sum: :sum', ['count' => $result['count'], 'sum' => formatNumber($result['sum'] ?? 0)]));

        if (isset($result['source_removed'])) {
            if ($result['source_removed']) {
                $response->with('info', __('Source subject removed successfully.'));
            } else {
                $response->with('warning', __('Source subject could not be removed. It may have children or other dependencies.'));
            }
        }

        return $response;
    }
}
