<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $customerCount = CustomerGroup::withCount('customers')->get()->sum('customers_count');

        $invoiceCount = Invoice::count();
        $documentCount = Document::count();
        $productCount = ProductGroup::withCount('products')->get()->sum('products_count');

        $cashBooks = Subject::where('parent_id', config('amir.cash_book'))->get();
        $banks = Subject::where('parent_id', config('amir.bank'))->get();

        $latestInvoices = Invoice::latest()->limit(10)->get();

        return view('home', compact(
            'customerCount',
            'invoiceCount',
            'documentCount',
            'productCount',
            'latestInvoices',
            'cashBooks',
            'banks',
        ));
    }


    public function subjectDetail(Request $request)
    {
        $data = $request->validate(
            [
                'cash_book' => 'required|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );
        $subjectId = $data['cash_book'];
        $duration = intval($data['duration']);
        $timeFilter = now()->subMonths($duration * 3);

        $transactions = Transaction::where('subject_id', $subjectId)
            ->where('created_at', '>=', $timeFilter)
            ->selectRaw('DATE(created_at) as date, SUM(value) as total_value')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total_value', 'date');

        return response()->json([
            'labels' => $transactions->keys(),
            'datas' => $transactions->values(),
            'sum' => $transactions->sum(),
        ]);
    }
}
