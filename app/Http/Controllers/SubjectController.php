<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct() {}

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

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'code' => 'nullable|string|max:3',
        ]);

        $data = [
            'name' => $validatedData['name'],
            'parent_id' => $validatedData['parent_id'] ?? null,
        ];

        if (! empty($validatedData['code'])) {
            $data['code'] = str_pad($validatedData['code'], 3, '0', STR_PAD_LEFT);
        }

        $subject = app(SubjectService::class)->createSubject($data);

        $redirectUrl = route('subjects.index');
        if ($subject->parent_id) {
            $redirectUrl .= '?parent_id='.$subject->parent_id;
        }

        return redirect($redirectUrl)->with('success', __('Subject with code :code created successfully.', ['code' => $subject->formattedCode()]));
    }

    public function edit(Subject $subject)
    {
        $parentSubject = $subject->parent;
        $subjects = Subject::whereIsRoot()->with('children')->orderBy('code', 'asc')->get();

        return view('subjects.edit', compact('subject', 'parentSubject', 'subjects'));
    }

    public function update(Request $request, Subject $subject)
    {
        $validatedData = $request->validate([
            'code' => 'required|max:20',
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'type' => 'required|in:debtor,creditor,both',
        ]);

        try {
            $updatedSubject = app(SubjectService::class)->editSubject($subject, $validatedData);

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

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
            'allSelectable' => 'sometimes|boolean',
            'level' => 'sometimes|integer|min:1',
        ]);

        $query = $validated['q'];
        $allSelectable = $validated['allSelectable'] ?? false;
        $maxLevel = $validated['level'] ?? null;

        $subjects = Subject::select('id', 'name', 'code', 'parent_id')->orderBy('code')->get();

        $subjectsById = $subjects->keyBy('id');
        $childrenMap = [];

        foreach ($subjects as $subject) {
            $parentKey = $subject->parent_id ?: 0;
            $childrenMap[$parentKey][] = $subject;
        }

        $allowedIds = null;
        if ($query !== '') {
            $allowedIds = [];
            $q = mb_strtolower($query);

            foreach ($subjects as $subject) {
                $name = mb_strtolower($subject->name);
                $code = mb_strtolower($subject->code);

                if (str_contains($name, $q) || str_contains($code, $q)) {
                    $allowedIds[$subject->id] = true;

                    $current = $subject;
                    while ($current->parent_id && $subjectsById->has($current->parent_id)) {
                        $current = $subjectsById[$current->parent_id];
                        $allowedIds[$current->id] = true;
                    }
                }
            }
        }

        $buildTree = function ($parentId, ?int $levelLeft = null) use (&$buildTree, $childrenMap, $allowedIds, $allSelectable) {
            $items = $childrenMap[$parentId] ?? [];
            $result = [];

            foreach ($items as $subject) {
                if (! is_null($allowedIds) && ! isset($allowedIds[$subject->id])) {
                    continue;
                }

                $nextLevelLeft = $levelLeft;
                if (! is_null($nextLevelLeft)) {
                    $nextLevelLeft = $nextLevelLeft - 1;
                }

                $children = [];
                if (is_null($nextLevelLeft) || $nextLevelLeft > 0) {
                    $children = $buildTree($subject->id, $nextLevelLeft);
                }

                $result[] = [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'parent_id' => $subject->parent_id,
                    'selectable' => $allSelectable || ! empty($children),
                    'children' => $children,
                ];
            }

            return $result;
        };

        return response()->json($buildTree(0, $maxLevel));
    }
}
