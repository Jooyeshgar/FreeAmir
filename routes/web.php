<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/login', [Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [Controllers\Auth\LoginController::class, 'login']);
Route::get('/logout', [Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::group(['middleware' => ['auth', 'check-permission']], function () {
    Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/home/cash-banks', [Controllers\HomeController::class, 'cashAndBanksBalances'])->name('home.cash-banks');
    Route::get('/home/bank-account', [Controllers\HomeController::class, 'bankAccount'])->name('home.bank-account');
    Route::get('subjects/search', [Controllers\SubjectController::class, 'search'])->name('subjects.search');
    Route::get('subjects/search-code', [Controllers\SubjectController::class, 'searchCode'])->name('subjects.search-code');
    Route::resource('subjects', Controllers\SubjectController::class);
    Route::get('documents/sort-numbers', [Controllers\DocumentController::class, 'sortNumbers'])->name('documents.sort-numbers');
    Route::post('documents/sort-numbers/start', [Controllers\DocumentController::class, 'startSorting'])->name('documents.sort-numbers.start');
    Route::post('documents/sort-numbers/process', [Controllers\DocumentController::class, 'processSorting'])->name('documents.sort-numbers.process');
    Route::resource('documents', Controllers\DocumentController::class);
    Route::get('documents/{document}/print', [Controllers\DocumentController::class, 'print'])->name('documents.print');
    Route::get('documents/{document}/duplicate', [Controllers\DocumentController::class, 'duplicate'])->name('documents.duplicate');

    Route::resource('transactions', Controllers\TransactionController::class)->only(['index', 'show']);
    Route::get('products/search-product-group', [Controllers\ProductController::class, 'searchProductGroup'])->name('products.search-product-group');
    Route::resource('products', Controllers\ProductController::class);
    Route::resource('product-groups', Controllers\ProductGroupController::class);
    Route::get('services/search-service-group', [Controllers\ServiceController::class, 'searchServiceGroup'])->name('services.search-service-group');
    Route::resource('services', Controllers\ServiceController::class);
    Route::resource('service-groups', Controllers\ServiceGroupController::class);
    Route::resource('customers', Controllers\CustomerController::class);
    Route::resource('customer-groups', Controllers\CustomerGroupController::class);
    Route::resource('companies', Controllers\CompanyController::class);
    Route::get('bank-accounts/search-bank', [Controllers\BankAccountController::class, 'searchBank'])->name('bank-accounts.search-bank');
    Route::resource('bank-accounts', Controllers\BankAccountController::class);
    Route::resource('banks', Controllers\BankController::class);

    Route::get('invoices/search/{invoice_type}', [Controllers\InvoiceController::class, 'search'])->name('invoices.search');
    Route::get('invoices/get-items/{invoice}', [Controllers\InvoiceController::class, 'getItems'])->name('invoices.get-items');
    Route::get('invoices/search-customer', [Controllers\InvoiceController::class, 'searchCustomer'])->name('invoices.search-customer');
    Route::get('invoices/search-product-service', [Controllers\InvoiceController::class, 'searchProductService'])->name('invoices.search-product-service');

    Route::get('invoices/inactive', [Controllers\InvoiceController::class, 'inactiveInvoices'])->name('invoices.inactive');
    Route::get('invoices/inactive/approve', [Controllers\InvoiceController::class, 'approveInactiveInvoices'])->name('invoices.inactive.approve');
    Route::prefix('invoices')->group(function () {
        Route::get('ancillary-costs/search-customer', [Controllers\AncillaryCostController::class, 'searchCustomer'])->name('ancillary-costs.search-customer');
        Route::get('ancillary-costs/search-invoice', [Controllers\AncillaryCostController::class, 'searchInvoice'])->name('ancillary-costs.search-invoice');
        Route::get('ancillary-costs/get-products/{invoice_id}', [Controllers\AncillaryCostController::class, 'getBuyInvoiceProducts'])->name('ancillary-costs.get-products');
        Route::get('ancillary-costs/{ancillary_cost}/change-status/{status}', [Controllers\AncillaryCostController::class, 'changeStatus'])->name('ancillary-costs.change-status')->middleware('can:ancillary-costs.approve');
        Route::get('ancillary-costs/', [Controllers\AncillaryCostController::class, 'index'])->name('ancillary-costs.index');
        Route::get('{invoice}/ancillary-costs/create', [Controllers\AncillaryCostController::class, 'create'])->name('invoices.ancillary-costs.create');
        Route::post('{invoice}/ancillary-costs', [Controllers\AncillaryCostController::class, 'store'])->name('invoices.ancillary-costs.store');
        Route::get('{invoice}/ancillary-costs/{ancillary_cost}', [Controllers\AncillaryCostController::class, 'show'])->name('invoices.ancillary-costs.show');
        Route::get('{invoice}/ancillary-costs/{ancillary_cost}/edit', [Controllers\AncillaryCostController::class, 'edit'])->name('invoices.ancillary-costs.edit');
        Route::put('{invoice}/ancillary-costs/{ancillary_cost}', [Controllers\AncillaryCostController::class, 'update'])->name('invoices.ancillary-costs.update');
        Route::delete('{invoice}/ancillary-costs/{ancillary_cost}', [Controllers\AncillaryCostController::class, 'destroy'])->name('invoices.ancillary-costs.destroy');
    });
    Route::resource('invoices', Controllers\InvoiceController::class)->except(['index']);
    Route::get('invoices/{invoice}/conflicts', [Controllers\InvoiceController::class, 'conflicts'])->name('invoices.conflicts');
    Route::get('invoices/{invoice}/conflicts/{type}', [Controllers\InvoiceController::class, 'showMoreConflictsByType'])->name('invoices.conflicts.more');
    Route::get('invoices/{invoice}/groupAction', [Controllers\InvoiceController::class, 'groupAction'])->name('invoices.groupAction');
    Route::get('invoices/{invoice}/print', [Controllers\InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/change-status/{status}', [Controllers\InvoiceController::class, 'changeStatus'])->name('invoices.change-status')->middleware('can:invoices.approve');
    Route::group(['prefix' => 'management'], function () {
        Route::resource('users', Controllers\Management\UserController::class);
        Route::resource('permissions', Controllers\Management\PermissionController::class)->except(['show']);
        Route::resource('roles', Controllers\Management\RoleController::class)->except(['show']);
        Route::resource('configs', Controllers\ConfigController::class);
    });

    Route::resource('org-charts', Controllers\OrgChartController::class);

    Route::group(['prefix' => 'salary'], function () {
        Route::resource('tax-slabs', Controllers\TaxSlabController::class);
        Route::resource('work-sites', Controllers\WorkSiteController::class)->except(['show']);
        Route::resource('work-site-contracts', Controllers\WorkSiteContractController::class)->except(['show']);
        Route::resource('public-holidays', Controllers\PublicHolidayController::class)->except(['show']);
    });
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('ledger', [Controllers\ReportsController::class, 'ledger'])->name('ledger');
        Route::get('journal', [Controllers\ReportsController::class, 'journal'])->name('journal');
        Route::get('sub-ledger', [Controllers\ReportsController::class, 'subLedger'])->name('sub-ledger');
        Route::get('trial-balance', [Controllers\ReportsController::class, 'trialBalance'])->name('trial-balance');
        Route::get('trial-balance.print', [Controllers\ReportsController::class, 'printTrialBalance'])->name('trial-balance.print');
        Route::get('documents', [Controllers\ReportsController::class, 'documents'])->name('documents');
        Route::get('result', [Controllers\ReportsController::class, 'result'])->name('result');
    });
    Route::get('change-company/{company}', [Controllers\CompanyController::class, 'setActiveCompany'])->name('change-company');

    Route::group(['prefix' => 'invoices', 'as' => 'invoices.index'], function () {
        Route::get('', [Controllers\InvoiceController::class, 'index']);
    });

    Route::prefix('customers/{customer}/comments')->as('comments.')->controller(Controllers\CommentController::class)->group(function () {
        Route::get('', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('', 'store')->name('store');
        Route::get('{comment}/edit', 'edit')->name('edit');
        Route::put('{comment}', 'update')->name('update');
        Route::delete('{comment}', 'destroy')->name('destroy');
    });

    Route::prefix('documents/{document}/files')->as('documents.files.')->controller(Controllers\DocumentFileController::class)->group(function () {
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('{documentFile}/edit', 'edit')->name('edit');
        Route::put('{documentFile}', 'update')->name('update');
        Route::delete('{documentFile}', 'destroy')->name('destroy');
        Route::get('{documentFile}/view', 'view')->name('view');
        Route::get('{documentFile}/download', 'download')->name('download');
    });
});
