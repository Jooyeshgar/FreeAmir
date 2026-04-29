<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\HomeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $service) {}

    public function seedDemoData()
    {
        abort_unless(config('app.debug'), 404);

        if (Document::count() !== 0) {
            return redirect()->route('home')->with('error', 'نمیتوان دیتاهای آزمایشی را به دیتابیس پر اضافه کرد.');
        }

        try {
            Artisan::call('db:seed', ['--class' => 'DemoSeeder']);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', 'خطایی در اجرای پر کردن دیتابیس رخ داد.');
        }

        return redirect()->route('home')->with('success', 'داده های آزمایشی به دیتابیس اضافه شدند.');
    }

    public function refreshDatabase()
    {
        abort_unless(config('app.debug'), 404);

        try {
            Artisan::call('migrate:fresh', ['--seed' => true]);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', 'خطایی در ریفرش کردن دیتابیس رخ داد.');
        }

        return redirect()->route('home')->with('success', 'دیتابیس با موفقیت ریفرش شد.');
    }

    public function index()
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

        $hasDocument = \App\Models\Document::count() === 0;
        $isDebugMode = config('app.debug');

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
