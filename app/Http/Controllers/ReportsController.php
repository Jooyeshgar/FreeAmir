<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class ReportsController extends Controller
{
    public function __construct() {}

    public function ledger()
    {
        $subjects = Subject::orderBy('code', 'asc')->whereIsRoot()->get();
        return view('reports.ledger', compact('subjects'));
    }

    public function journal()
    {

        $subjects = [];
        return view('reports.journal', compact('subjects'));
    }

    public function subLedger()
    {
        $subjects = Subject::orderBy('code', 'asc')->get();
        return view('reports.subLedger', compact('subjects'));
    }

    public function result(Request $request)
    {
        $this->updateTree();

        $rules = [
            'report_for' => 'required',
            'report_type' => 'required',
        ];

        if ($request->report_for != 'Journal') {
            $rules['subject_id'] = 'required';
        }

        if ($request->report_type == 'between_numbers') {
            $rules['start_document_number'] = 'required';
            $rules['end_document_number'] = 'required';
        }

        if ($request->report_type == 'between_dates') {
            $rules['start_date'] = 'required';
            $rules['end_date'] = 'required';
        }

        if ($request->report_type == 'specific_date') {
            $rules['specific_date'] = 'required';
        }

        Validator::make($request->all(), $rules)->validate();

        $transactions = new Transaction();

        if ($request->subject_id && $request->report_for == 'subLedger') {
            $transactions = $transactions->where('subject_id', $request->subject_id);
        }
        if ($request->subject_id && $request->report_for == 'Ledger') {
            $subject = Subject::findOrFail($request->subject_id);

            $transactions = $transactions->whereHas('subject', function ($query) use ($subject) {
                // Get the subject and all its descendants using the nested set model
                $query->where('_lft', '>=', $subject->_lft)
                    ->where('_rgt', '<=', $subject->_rgt);
            });
        }

        if ($request->search) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->report_type == 'between_numbers') {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('number', '>=', $request->start_document_number)->where('number', '<=', $request->end_document_number);
            });
        }

        if ($request->report_type == 'between_dates') {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('date', '>=', jalali_to_gregorian_date($request->start_date))->where('date', '<=', jalali_to_gregorian_date($request->end_date));
            });
        }
        if ($request->report_type == 'specific_date') {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('date', '=', jalali_to_gregorian_date($request->specific_date));
            });
        }
        $transactions = $transactions->with('document', 'subject')->get();
        $transactionsChunk = $transactions->chunk(env('REPORT_ROW_SIZE', 26));

        if ($request->report_for == 'Journal') {
            return view('reports.journalReport', compact('transactionsChunk'));
        }
        return view('reports.ledgerReport', compact('transactionsChunk'));
    }

    private function updateTree()
    {
        //        this function should remove. its just for fix tree one time.
        $sub = Subject::all();
        foreach ($sub as $s) {
            $s->fixTree();
        }
    }
}
