<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class ReportsController extends Controller
{
    public function all()
    {
        $subjects = Subject::where('parent_id', '=', 0)->get();
        return view('reports.all', compact('subjects'));
    }

    public function roozname()
    {

        $subjects = [];
        return view('reports.roozname', compact('subjects'));

    }

    public function moien()
    {
        $subjects = Subject::where('parent_id', '!=', 0)->get();
        return view('reports.moein', compact('subjects'));
    }

    public function result(Request $request)
    {
        $this->updateTree();
        $validator = Validator::make($request->all(), [
            'report_for' => 'required',
            'report_type' => 'required',
            'subject_id' => '',
        ]);
        $validator->sometimes('subject_id', 'required', function ($input) {
            return $input->report_for != 'roozname';
        });

        $validator->sometimes(['start_document_number', 'end_document_number'], 'required', function ($input) {
            return $input->report_type == 'between_numbers';
        });

        $validator->sometimes(['start_date', 'end_date'], 'required', function ($input) {
            return $input->report_type == 'between_dates';
        });
        $validator->sometimes(['specific_date'], 'required', function ($input) {
            return $input->report_type == 'specific_date';
        });

        $validator->validate();

        $transactions = new Transaction();

        if ($request->subject_id && $request->report_for == 'moein') {
            $transactions = $transactions->where('subject_id', $request->subject_id);
        }
        if ($request->subject_id && $request->report_for == 'all') {
            $transactions = $transactions->whereHas('subject', function ($query) use ($request) {
                $query->whereHas('ancestors', function ($query) use ($request) {
                    $query->where('id', $request->subject_id)->whereIsRoot();
                });
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
        return view('reports.table', compact('transactions'));

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
