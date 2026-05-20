<?php

namespace App\Http\Controllers;

use App\Services\WarehouseDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseDashboardController extends Controller
{
    public function __construct(private readonly WarehouseDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $canViewAccounting = $request->user()->canAny([
            'documents.index',
            'documents.show',
            'reports.documents',
            'reports.journal',
            'reports.ledger',
            'reports.trial-balance',
        ]);

        return view('warehouse.dashboard', [
            ...$this->dashboardService->dashboard($canViewAccounting),
            'canViewAccounting' => $canViewAccounting,
        ]);
    }
}
