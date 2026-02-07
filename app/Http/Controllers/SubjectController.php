<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'subject_code' => 'nullable|string|max:3',
            'is_permanent' => 'required|in:Permanent,Temporary',
            'type' => 'required|in:debtor,creditor,both',
        ]);

        $validatedData['code'] = $validatedData['subject_code'] ?? null;
        $validatedData['is_permanent'] = $validatedData['is_permanent'] === 'Permanent';
        $validatedData['code'] = ! empty($validatedData['code']) ? str_pad($validatedData['code'], 3, '0', STR_PAD_LEFT) : null;

        $parentSubject = $validatedData['parent_id'] ? Subject::find($validatedData['parent_id']) : null;
        $allowedTypes = $this->subjectService->getAllowedTypesForSubject($parentSubject);

        if (! in_array($validatedData['type'], $allowedTypes)) {
            return redirect()->back()->withErrors(['type' => __('The selected type is not allowed according to the chosen parent subject.')])->withInput();
        }

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
        $subjects = Subject::whereIsRoot()->with('children')->orderBy('code')->get();

        return view('subjects.edit', compact('subject', 'parentSubject', 'subjects'));
    }

    public function update(Request $request, Subject $subject)
    {
        $validatedData = $request->validate([
            'subject_code' => 'nullable|max:3',
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'type' => 'required|in:debtor,creditor,both',
            'is_permanent' => 'required|in:Permanent,Temporary',
        ]);

        $validatedData['is_permanent'] = $validatedData['is_permanent'] === 'Permanent';
        $validatedData['code'] = $validatedData['subject_code'];
        $allowedTypes = $this->subjectService->getAllowedTypesForSubject($validatedData['parent_id'] ? Subject::find($validatedData['parent_id']) : null);

        if (! in_array($validatedData['type'], $allowedTypes)) {
            return redirect()->back()->withErrors(['type' => __('The selected type is not allowed according to the chosen parent subject.')]);
        }

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
