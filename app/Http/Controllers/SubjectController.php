<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        if ($request->has('parent_id')) {
            $subjects = Subject::find($request->get('parent_id'))->children()->get();
        } else {
            $subjects = Subject::whereIsRoot()->get();
        }

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        $parentSubjects = Subject::where('parent_id', null)->get();

        return view('subjects.create', compact('parentSubjects'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|max:20|unique:subjects',
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'type' => 'required|in:debtor,creditor,both',
        ]);
        $validatedData['company_id'] = session('active-company-id');
        Subject::create($validatedData);

        return redirect()->route('subjects.index')->with('success', 'Subject created successfully.');
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

        return redirect()->route('subjects.index')->with('success', 'Subject updated successfully.');
    }


    public function destroy(Subject $subject)
    {
        $subject->delete();

        return redirect()->route('subjects.index')->with('success', 'Subject deleted successfully.');
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $subjects = Subject::with('subSubjects')->where('code', 'like', '%' . $query . '%')
            ->orWhere('name', 'like', '%' . $query . '%')
            ->get();
        
        return response()->json($subjects);
    }
}
