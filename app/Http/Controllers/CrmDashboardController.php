<?php

namespace App\Http\Controllers;

use App\Services\CrmDashboardService;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function __construct(private readonly CrmDashboardService $service) {}

    public function index(): View
    {
        return view('crm.dashboard', $this->service->dashboard());
    }
}
