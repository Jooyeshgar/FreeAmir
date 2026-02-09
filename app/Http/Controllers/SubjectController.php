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
        $currentParent = null;

        if ($request->has('parent_id')) {
            $currentParent = Subject::find($request->get('parent_id'));
            $subjects = $currentParent->children()->with('subjectable');
        } else {
            $subjects = Subject::whereIsRoot()->with('subjectable');
        }

        $subjects = $subjects->orderBy('code')->get();

        return view('subjects.index', compact('subjects', 'currentParent'));
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
        $subjects = $this->buildSubjectOptionsForSelectBox($subjects);

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

    /**
     * Build a subject tree suitable for the subject-select component.
     */
    private function buildSubjectOptionsForSelectBox(Collection $subjects): array
    {
        $rootKey = 'root';
        $grouped = $subjects->groupBy(function ($subject) use ($rootKey) {
            return empty($subject->parent_id) ? $rootKey : (string) $subject->parent_id;
        });

        $buildTree = function (string $parentKey) use (&$buildTree, $grouped): array {
            $children = $grouped->get($parentKey, collect());

            return $children->map(function ($subject) use (&$buildTree) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'parent_id' => $subject->parent_id,
                    'children' => $buildTree((string) $subject->id),
                ];
            })->values()->all();
        };

        return $buildTree($rootKey);
    }

    private function collectWithChildren(Collection $subjects): Collection
    {
        $result = $subjects->keyBy('id');

        $childIds = collect();
        $subjects->each(fn ($subject) => $childIds->push($subject->getAllDescendantIds()));

        $childIds = $childIds->flatten()->unique()->reject(fn ($id) => $result->has($id))->values();

        while ($childIds->isNotEmpty()) {
            // Iteratively load only missing children
            $children = Subject::query()->select(['id', 'name', 'code', 'parent_id'])->whereIn('id', $childIds)->get();

            foreach ($children as $child) {
                $result->put($child->id, $child);
            }

            $childIds = $children->pluck('id')->reject(fn ($id) => $result->has($id))->values();
        }

        return $result->values();
    }

    private function collectWithParents(Collection $subjects): Collection
    {
        $result = $subjects->keyBy('id');
        $parentIds = $subjects->pluck('parent_id')->filter()->unique()->values();

        while ($parentIds->isNotEmpty()) {
            // Iteratively load only missing parents
            $parents = Subject::query()->select(['id', 'name', 'code', 'parent_id'])->whereIn('id', $parentIds)->get();

            foreach ($parents as $parent) {
                $result->put($parent->id, $parent);
            }

            $parentIds = $parents->pluck('parent_id')->filter()->unique()->reject(fn ($id) => $result->has($id))->values();
        }

        return $result->values();
    }

    private function formatSubjects(Collection $subjects): array
    {
        $map = [];
        $tree = [];

        // Build a lookup table first for quick parent->child attachment
        foreach ($subjects as $subject) {
            $map[$subject->id] = [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'parent_id' => $subject->parent_id,
                'children' => [],
            ];
        }

        // Create the tree in one pass without extra DB queries
        foreach ($map as $id => &$node) {
            if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        return $tree;
    }

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

        $withChildrenMatched = $this->collectWithChildren($matched);
        $withParentsMatched = $this->collectWithParents($matched);

        // Include ancestors and descendants so the client can render a full tree
        $subjects = $withChildrenMatched->merge($withParentsMatched)->unique('id');

        return response()->json($this->formatSubjects($subjects));
    }
}
