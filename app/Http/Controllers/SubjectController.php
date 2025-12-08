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
        $query = $request->input('query');
        $subjects = Subject::with([
            'subSubjects' => function ($subQuery) use ($query) {
                $subQuery->where('code', 'like', '%'.$query.'%')
                    ->orWhere('name', 'like', '%'.$query.'%');
            },
        ])
            ->where(function ($parentQuery) use ($query) {
                $parentQuery->where('parent_id', null) // Ensure parent subjects
                    ->where(function ($innerQuery) use ($query) {
                        $innerQuery->where('code', 'like', '%'.$query.'%')
                            ->orWhere('name', 'like', '%'.$query.'%');
                    });
            })
            ->orWhereHas('subSubjects', function ($subQuery) use ($query) {
                $subQuery->where('code', 'like', '%'.$query.'%')
                    ->orWhere('name', 'like', '%'.$query.'%');
            })
            ->get()
            ->map(function ($subject) use ($query) {
                if (stripos($subject->name, $query) !== false || stripos($subject->code, $query) !== false) {
                    $subject->setRelation('subSubjects', $subject->subSubjects()->get());
                }

                return $subject;
            });

        return response()->json($subjects);
    }
}
