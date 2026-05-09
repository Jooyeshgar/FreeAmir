<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\HomeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $service) {}

    public function seedDemoData(): RedirectResponse
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

    public function refreshDatabase(): RedirectResponse
    {
        abort_if(! config('app.debug') || app()->isProduction(), 404);

        try {
            Artisan::call('migrate:fresh', ['--seed' => true]);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('An error occurred while refreshing the database.'));
        }

        return redirect()->route('home')->with('success', __('Refresh database completed successfully.'));
    }

    public function index(): View|RedirectResponse
    {
        if (! (auth()->user()->can('documents.show') or auth()->user()->can('products.index'))) {
            if (auth()->user()->can('employee-portal.dashboard')) {
                return redirect()->route('employee-portal.dashboard');
            }
            abort(403);
        }

        $cashTypes = ['both', 'bank', 'cash_book'];

        [$bankAccounts, $topTenBankAccountBalances] = $this->service->topTenBanksAccountBalances();

        $monthlyIncome = $this->service->getMonthlyIncome();
        $monthlyCost = $this->service->getMonthlyCost();

        $monthlySellAmount = $this->service->getMonthlyProductsStat();
        $monthlyWarehouse = $this->service->getMonthlyWarehouse();

        $popularProductsAndServices = $this->service->popularProductsAndServices();

        $sellAmountPerProducts = $this->service->getSellAmountPerProducts();

        ['incomeData' => $totalIncomesData, 'costData' => $totalCostsData, 'profit' => $profit] =
            $this->service->profitFromNonPermanentSubjects();

        $hasDocument = Document::exists();
        $isDebugMode = config('app.debug') && ! app()->isProduction();

        return view('home', compact(
            'hasDocument',
            'isDebugMode',
            'cashTypes',
            'bankAccounts',
            'topTenBankAccountBalances',
            'monthlyIncome',
            'monthlyCost',
            'monthlySellAmount',
            'monthlyWarehouse',
            'popularProductsAndServices',
            'sellAmountPerProducts',
            'totalIncomesData',
            'totalCostsData',
            'profit',
        ));
    }

    public function cashAndBanksBalances(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'duration' => 'required|integer|in:1,2,3,4',
                'type' => 'required|in:cash_book,bank,both',
            ]
        );

        return $this->service->cashAndBanksBalances($data['type'], intval($data['duration']));
    }

    public function bankAccount(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'subject_id' => 'required|integer|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );

        return $this->service->balanceForSubjectIds([$data['subject_id']], intval($data['duration']));
    }

    public function hideDemoAlert(): JsonResponse
    {
        session(['hide_empty_database_demo_alert' => true]);

        return response()->json(['ok' => true]);
    }
}
