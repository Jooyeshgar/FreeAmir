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
        $validated = $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
            'category_id' => ['nullable', 'integer', 'exists:product_groups,id'],
            'status' => ['nullable', 'string', 'in:below_reorder,stagnant,normal'],
        ]);

        $data = $this->dashboardService->dashboard($validated);

        return view('warehouse.dashboard', $data);
    }
}
