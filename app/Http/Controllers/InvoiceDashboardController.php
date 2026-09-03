<?php

namespace App\Http\Controllers;

use App\Services\InvoiceDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceDashboardController extends Controller
{
    public function __construct(private readonly InvoiceDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
        ]);

        return view('invoices.dashboard', $this->dashboardService->dashboard($validated));
    }
}
