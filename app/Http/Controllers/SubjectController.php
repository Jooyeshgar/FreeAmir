<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct() {}

    public function index(Request $request)
    {
        if ($request->has('parent_id')) {
            $subjects = Subject::find($request->get('parent_id'))->children()->with('subjectable')->get();
        } else {
            $subjects = Subject::whereIsRoot()->with('subjectable')->get();
        }

        return view('subjects.index', compact('subjects'));
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
        if ($request['code']) {
            $request['code'] = str_pad($request['code'], 3, '0', STR_PAD_LEFT);
        }

        $validatedData = $request->validate([
            'code' => 'nullable|max:3',
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'type' => 'required|in:debtor,creditor,both',
        ]);

        Subject::create($validatedData);

        return redirect()->route('subjects.index')->with('success', __('Subject created successfully.'));
    }

    public function edit(Subject $subject)
    {
        $parentSubjects = Subject::where('parent_id', null)->get();

        return view('subjects.edit', compact('subject', 'parentSubjects'));
    }

    public function update(Request $request, Subject $subject)
    {
        $validatedData = $request->validate([
            'code' => 'required|max:20|unique:subjects,code,' . $subject->id,
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'type' => 'required|in:debtor,creditor,both',
        ]);

        $subject->update($validatedData);

        return redirect()->back()->with('success', __('Subject updated successfully.'));
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
                $subQuery->where('code', 'like', '%' . $query . '%')
                    ->orWhere('name', 'like', '%' . $query . '%');
            }
        ])
            ->where(function ($parentQuery) use ($query) {
                $parentQuery->where('parent_id', null) // Ensure parent subjects
                    ->where(function ($innerQuery) use ($query) {
                        $innerQuery->where('code', 'like', '%' . $query . '%')
                            ->orWhere('name', 'like', '%' . $query . '%');
                    });
            })
            ->orWhereHas('subSubjects', function ($subQuery) use ($query) {
                $subQuery->where('code', 'like', '%' . $query . '%')
                    ->orWhere('name', 'like', '%' . $query . '%');
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
