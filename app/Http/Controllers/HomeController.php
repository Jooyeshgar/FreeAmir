<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Document;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        $customerCount = Customer::count();
        $invoiceCount = Invoice::count();
        $documentCount = Document::count();
        $productCount = Product::count();

        $latestInvoices = Invoice::latest()->limit(10)->get();

        return view('home', compact(
            'customerCount',
            'invoiceCount',
            'documentCount',
            'productCount',
            'latestInvoices'
        ));
    }
}
