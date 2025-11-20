<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/login', [Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [Controllers\Auth\LoginController::class, 'login']);
Route::get('/logout', [Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::group(['middleware' => ['auth', 'check-permission']], function () {
    Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');
    Route::post('/home/subject-detail', [Controllers\HomeController::class, 'subjectDetail'])->name('home.subject-detail');
    Route::resource('subjects', Controllers\SubjectController::class);
    Route::post('subjects/search', [Controllers\SubjectController::class, 'search'])->name('subjects.search');
    Route::resource('documents', Controllers\DocumentController::class);
    Route::get('documents/{document}/duplicate', [Controllers\DocumentController::class, 'duplicate'])->name('documents.duplicate');
    Route::resource('transactions', Controllers\TransactionController::class)->only(['index', 'show']);
    Route::resource('products', Controllers\ProductController::class);
    Route::resource('product-groups', Controllers\ProductGroupController::class);
    Route::resource('services', Controllers\ServiceController::class);
    Route::resource('service-groups', Controllers\ServiceGroupController::class);
    Route::resource('customers', Controllers\CustomerController::class);
    Route::resource('customer-groups', Controllers\CustomerGroupController::class);
    Route::resource('companies', Controllers\CompanyController::class);
    Route::resource('bank-accounts', Controllers\BankAccountController::class);
    Route::resource('banks', Controllers\BankController::class);
    Route::resource('invoices', Controllers\InvoiceController::class)->except(['index', 'create']);
    Route::get('invoices/{invoice}/print', [Controllers\InvoiceController::class, 'print'])->name('invoices.print');
    Route::resource('ancillary-costs', Controllers\AncillaryCostController::class)->except(['show']);
    Route::get('ancillary-costs/get-products/{invoice_id}', [Controllers\AncillaryCostController::class, 'getBuyInvoiceProducts'])->name('ancillary-costs.get-products');
    Route::group(['prefix' => 'management'], function () {
        Route::resource('users', Controllers\Management\UserController::class);
        Route::resource('permissions', Controllers\Management\PermissionController::class)->except(['show']);
        Route::resource('roles', Controllers\Management\RoleController::class)->except(['show']);
        Route::resource('configs', Controllers\ConfigController::class);
    });
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('ledger', [Controllers\ReportsController::class, 'ledger'])->name('ledger');
        Route::get('journal', [Controllers\ReportsController::class, 'journal'])->name('journal');
        Route::get('sub-ledger', [Controllers\ReportsController::class, 'subLedger'])->name('sub-ledger');
        Route::get('documents', [Controllers\ReportsController::class, 'documents'])->name('documents');
        Route::get('result', [Controllers\ReportsController::class, 'result'])->name('result');
    });
    Route::get('change-company/{company}', [Controllers\CompanyController::class, 'setActiveCompany'])->name('change-company');

    Route::group(['prefix' => 'invoices/create', 'as' => 'invoices.create'], function () {
        Route::get('{invoice_type}', [Controllers\InvoiceController::class, 'create']);
    });

    Route::group(['prefix' => 'invoices', 'as' => 'invoices.index'], function () {
        Route::get('', [Controllers\InvoiceController::class, 'index']);
    });

    Route::get('/search', [Controllers\ModelSelectController::class, '__invoke']);
});
