<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\HomeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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

    public function seedDemoData()
    {
        abort_if(! config('app.debug') || app()->isProduction(), 404);

        if (Document::exists()) {
            return redirect()->route('home')->with('error', __('Cannot add demo data to a non-empty database.'));
        }

        try {
            Artisan::call('db:seed', ['--class' => 'DemoSeeder']);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('An error occurred while seeding demo data.'));
        }

        return redirect()->route('home')->with('success', __('Demo data has been added to the database.'));
    }

    public function refreshDatabase()
    {
        abort_if(! config('app.debug') || app()->isProduction(), 404);

        try {
            Artisan::call('migrate:fresh', ['--seed' => true]);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('An error occurred while refreshing the database.'));
        }

        return redirect()->route('home')->with('success', __('Refresh database completed successfully.'));
    }

    public function index()
    {
        $user = auth()->user();

        // Use can() (not Spatie's hasAnyPermission) so AppServiceProvider's Gate::before
        // hook for Super-Admin is honored.
        $hasBusinessPerms = collect(self::BUSINESS_PERMISSIONS)->contains(fn ($perm) => $user->can($perm));
        $canSeePersonalPortal = $user->can('employee-portal.dashboard') && ! $hasBusinessPerms;

        $canFinancial = $user->can('documents.show');
        $canSales = $user->can('invoices.index') || $user->can('products.index');
        $canInventory = $user->can('products.index');
        $canPopularItems = $user->can('products.index') || $user->can('services.index');

        if (! $hasBusinessPerms && ! $canSeePersonalPortal) {
            abort(403);
        }

        $cashTypes = ['both', 'bank', 'cash_book'];

        $data = [
            'cashTypes' => $cashTypes,
            'hasBusinessPerms' => $hasBusinessPerms,
            'canSeePersonalPortal' => $canSeePersonalPortal,
            'canFinancial' => $canFinancial,
            'canSales' => $canSales,
            'canInventory' => $canInventory,
            'canPopularItems' => $canPopularItems,
            'hasDocument' => Document::exists(),
            'isDebugMode' => config('app.debug') && ! app()->isProduction(),
        ];

        if ($canFinancial) {
            [$bankAccounts, $topTenBankAccountBalances] = $this->service->topTenBanksAccountBalances();

            ['incomeData' => $totalIncomesData, 'costData' => $totalCostsData, 'profit' => $profit] =
                $this->service->profitFromNonPermanentSubjects();

            $data += [
                'bankAccounts' => $bankAccounts,
                'topTenBankAccountBalances' => $topTenBankAccountBalances,
                'monthlyIncome' => $this->service->getMonthlyIncome(),
                'monthlyCost' => $this->service->getMonthlyCost(),
                'totalIncomesData' => $totalIncomesData,
                'totalCostsData' => $totalCostsData,
                'profit' => $profit,
            ];
        }

        if ($canSales) {
            $data['monthlySellAmount'] = $this->service->getMonthlyProductsStat();
            $data['sellAmountPerProducts'] = $this->service->getSellAmountPerProducts();
        }

        if ($canInventory) {
            $data['monthlyWarehouse'] = $this->service->getMonthlyWarehouse();
        }

        if ($canPopularItems) {
            $data['popularProductsAndServices'] = $this->service->popularProductsAndServices();
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

    public function cashAndBanksBalances(Request $request)
    {
        $data = $request->validate(
            [
                'duration' => 'required|integer|in:1,2,3,4',
                'type' => 'required|in:cash_book,bank,both',
            ]
        );

        return $this->service->cashAndBanksBalances($data['type'], intval($data['duration']));
    }

    public function bankAccount(Request $request)
    {
        $data = $request->validate(
            [
                'subject_id' => 'required|integer|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );

        return $this->service->balanceForSubjectIds([$data['subject_id']], intval($data['duration']));
    }
}
