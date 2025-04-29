<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Transaction;
use App\Models\Document; // Import Document model
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

    public function documents()
    {
        return view('reports.documents');
    }

    public function result(Request $request)
    {
        $rules = [
            'report_for' => 'required|in:Journal,Ledger,subLedger,Document', // Added Document
            'report_type' => 'required', // e.g., between_numbers, between_dates, specific_date, all
        ];

        if ($request->report_for != 'Journal' && $request->report_for != 'Document') {
            // Subject is required for Ledger and subLedger
            $rules['subject_id'] = 'required';
        }

        if ($request->report_type == 'between_numbers') {
            $rules['start_document_number'] = 'required|numeric';
            $rules['end_document_number'] = 'required|numeric';
        } elseif ($request->report_type == 'between_dates') {
            $rules['start_date'] = 'required|date_format:Y/m/d'; // Adjust format if needed
            $rules['end_date'] = 'required|date_format:Y/m/d';   // Adjust format if needed
        } elseif ($request->report_type == 'specific_date') {
            $rules['specific_date'] = 'required|date_format:Y/m/d'; // Adjust format if needed
        } elseif ($request->report_type == 'specific_number') { // Added specific number type
            $rules['specific_document_number'] = 'required|numeric';
        }

        if (!in_array($request->report_type, ['between_numbers', 'between_dates', 'specific_date', 'specific_number', 'all'])) {
            // If report_type is something else, maybe it requires dates/numbers?
            // Add more specific validation if needed
        }


        Validator::make($request->all(), $rules)->validate();

        if ($request->report_for == 'Document') {
            $documents = Document::query(); // Start building query for Documents

            // Apply filters based on report_type
            if ($request->report_type == 'between_numbers') {
                $documents->whereBetween('number', [$request->start_document_number, $request->end_document_number]);
            } elseif ($request->report_type == 'specific_number') {
                $documents->where('number', $request->specific_document_number);
            } elseif ($request->report_type == 'between_dates') {
                // Convert Jalali dates to Gregorian for database query
                $startDate = jalali_to_gregorian_date($request->start_date);
                $endDate = jalali_to_gregorian_date($request->end_date);
                $documents->whereBetween('date', [$startDate, $endDate]);
            } elseif ($request->report_type == 'specific_date') {
                $specificDate = jalali_to_gregorian_date($request->specific_date);
                $documents->where('date', $specificDate);
            }
            // If report_type is 'all', no date/number filter is applied here

            // Apply search filter to document title
            if ($request->search) {
                $documents->where('title', 'like', '%' . $request->search . '%');
            }

            // Order documents for display
            $documents->orderBy('date', 'asc')->orderBy('number', 'asc');

            // Eager load necessary relationships for the document template
            // Need transactions and their subjects, plus creator and approver
            $documents = $documents->with(['transactions.subject', 'creator', 'approver'])->get();

            // Pass the collection of documents to the new report view
            return view('reports.documentReport', compact('documents'));
        }


        $transactions = Transaction::query();

        if ($request->subject_id && $request->report_for == 'subLedger') {
            if ($request->subject_id) {
                $subject = Subject::findOrFail($request->subject_id);

                if ($request->report_for == 'subLedger' || $request->report_for == 'Ledger') {
                    $subjectIds = $subject->getAllDescendantIds();

                    $transactions = $transactions->whereIn('subject_id', $subjectIds);
                }
            }
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
}
