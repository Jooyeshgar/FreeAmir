<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:invoices.*');
        $this->middleware('permission:invoices.edit')->only(['edit', 'update']);
        $this->middleware('permission:invoices.create')->only(['create', 'store']);
        $this->middleware('permission:invoices.destroy')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $invoices = Invoice::with('customer', 'document')->latest()->paginate(10);

        // Optional: Filter by search query
        $searchTerm = $request->get('q');
        if ($searchTerm) {
            $invoices = $invoices->where('code', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%");
        }

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $customers = Customer::pluck('name', 'id');
        $documents = Document::pluck('number', 'id');

        return view('invoices.create', compact('customers', 'documents'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:invoices|max:255',
            'date' => 'required|date',
            'document_id' => 'required|integer|exists:documents,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'addition' => 'numeric|nullable',
            'subtraction' => 'numeric|nullable',
            'tax' => 'numeric|nullable',
            'cash_payment' => 'boolean',
            'ship_date' => 'nullable|date',
            'ship_via' => 'string|nullable|max:255',
            'permanent' => 'boolean',
            'description' => 'string|nullable|max:255',
            'is_sell' => 'boolean',
            // Add validation for vat and amount if needed
        ]);

        $invoice = Invoice::create($request->all());

        return redirect()->route('invoices.index')->with('success', 'Invoice created successfully!');
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice)
    {
        $invoice->load('customer', 'document'); // Eager load customer and document data

        return view('invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Invoice $invoice)
    {
        $invoice->load('customer', 'document'); // Eager load customer and document data

        $customers = Customer::pluck('name', 'id');
        $documents = Document::pluck('number', 'id');

        return view('invoices.edit', compact('invoice', 'customers', 'documents'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'code' => 'required|unique:invoices,code,'.$invoice->id.'|max:255',
            'date' => 'required|date',
            'document_id' => 'required|integer|exists:documents,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'addition' => 'numeric|nullable',
            'subtraction' => 'numeric|nullable',
            'tax' => 'numeric|nullable',
            'cash_payment' => 'boolean',
            'ship_date' => 'nullable|date',
            'ship_via' => 'string|nullable|max:255',
            'permanent' => 'boolean',
            'description' => 'string|nullable|max:255',
            'is_sell' => 'boolean',
            // Add validation for vat and amount if needed
        ]);

        $invoice->update($request->all());

        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully!');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
    }
}
