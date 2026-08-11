<?php

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HomeController extends Controller
{
    /**
     * Permissions that mark a user as a "business" user. When any of these
     * are present, the personal portal section is hidden so the dashboard
     * stays focused on the user's higher-priority responsibilities.
     */
    private const BUSINESS_PERMISSIONS = [
        'documents.show',
        'products.index',
        'services.index',
        'invoices.index',
        'customers.index',
        'bank-accounts.index',
        'reports.ledger',
    ];

    public function __construct(private readonly HomeService $service) {}

    public function managementDashboard(Request $request): View
    {
        $request->session()->put('interface_mode', 'management');

        return view('super-admin.dashboard', $this->service->superAdminOverview());
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->can('access-super-admin-panel')) {
            $hasCurrentWorkspace = $user->companies()->whereKey(getActiveCompany())->where('fiscal_year', toEnglish(jdate('Y')))->exists();

            if (! $hasCurrentWorkspace) {
                return redirect()->route('management.dashboard');
            }
        }

        $request->session()->put('interface_mode', 'workspace');

        // Use can() (not Spatie's hasAnyPermission) so AppServiceProvider's
        // platform-access capability hook is honored.
        $hasBusinessPerms = collect(self::BUSINESS_PERMISSIONS)->contains(fn ($perm) => $user->can($perm));
        $canSeePersonalPortal = $user->can('employee-portal.dashboard') && ! $hasBusinessPerms;

        $canFinancial = $user->can('documents.show');
        $canSales = $user->can('invoices.index');
        $canInventory = $user->can('products.index');
        $canServices = $user->can('services.index');
        $canCustomers = $user->can('customers.index');

        $homeVariant = match (true) {
            $user->can('access-super-admin-panel') => 'platform',
            $canFinancial && $user->can('configs.index') => 'admin',
            $canFinancial => 'accounting',
            $canSales && $canInventory => 'operations',
            $canSales => 'sales',
            $canInventory => 'inventory',
            $canServices => 'services',
            $canCustomers => 'crm',
            $canSeePersonalPortal => 'employee',
            default => 'business',
        };

        if (! $hasBusinessPerms && ! $canSeePersonalPortal) {
            abort(403);
        }

        $data = [
            'hasBusinessPerms' => $hasBusinessPerms,
            'canSeePersonalPortal' => $canSeePersonalPortal,
            'canFinancial' => $canFinancial,
            'canSales' => $canSales,
            'canInventory' => $canInventory,
            'canServices' => $canServices,
            'canCustomers' => $canCustomers,
            'homeVariant' => $homeVariant,
        ];

        if ($canFinancial) {
            $quickAccessAreas = collect([
                'accounting' => $canFinancial,
                'sales' => $canSales,
                'inventory' => $canInventory,
                'services' => $canServices,
                'customers' => $canCustomers,
            ])->filter()->keys()->all();

            $data['quickAccessRecentData'] = $this->service->latestQuickAccessData($quickAccessAreas);
        }

        if ($canSeePersonalPortal) {
            $personal = $this->service->employeePersonalData($user);

            if ($personal) {
                $data += $personal;
                $data['hasPersonalData'] = true;
            } else {
                $data['hasPersonalData'] = false;
            }
        }

        return view('home', $data);
    }

    /**
     * Return one explicitly requested private dashboard metric.
     */
    public function summaryMetric(string $metric): JsonResponse
    {
        Validator::make(['metric' => $metric], ['metric' => ['in:profit,sales,inventory,expenses,purchases,average_sales,average_purchases,inventory_retail,inventory_average_cost,inventory_average_price,employee_net_payment,employee_earnings,employee_deductions,employee_tax']])->validate();
        $user = auth()->user();

        $permission = match ($metric) {
            'profit' => 'documents.show',
            'sales' => 'invoices.index',
            'inventory' => 'products.index',
            'expenses' => 'documents.show',
            'purchases' => 'invoices.index',
            'average_sales', 'average_purchases' => 'invoices.index',
            'inventory_retail', 'inventory_average_cost', 'inventory_average_price' => 'products.index',
            'employee_net_payment', 'employee_earnings', 'employee_deductions', 'employee_tax' => 'employee-portal.dashboard',
        };

        abort_unless($user->can($permission), 403);

        $value = match ($metric) {
            'profit' => $this->service->profitFromNonPermanentSubjects()['profit'],
            'sales' => $this->service->totalSellAmount(),
            'inventory' => $this->service->totalWarehouseValue(),
            'expenses' => array_sum($this->service->profitFromNonPermanentSubjects()['costData']),
            'purchases' => $this->service->totalBuyAmount(),
            'average_sales' => $this->service->averageSellAmount(),
            'average_purchases' => $this->service->averageBuyAmount(),
            'inventory_retail' => $this->service->totalWarehouseRetailValue(),
            'inventory_average_cost' => $this->service->averageWarehouseUnitCost(),
            'inventory_average_price' => $this->service->averageWarehouseSellingPrice(),
            'employee_net_payment' => $this->service->employeePayrollSummary($user)['net_payment'],
            'employee_earnings' => $this->service->employeePayrollSummary($user)['total_earnings'],
            'employee_deductions' => $this->service->employeePayrollSummary($user)['total_deductions'],
            'employee_tax' => $this->service->employeePayrollSummary($user)['income_tax_amount'],
        };

        return response()->json([
            'metric' => $metric,
            'formattedValue' => formatNumber($value),
            'unit' => config('amir.currency') ?? __('Rial'),
        ]);
    }
}
