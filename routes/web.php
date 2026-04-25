<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/login', [Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [Controllers\Auth\LoginController::class, 'login']);
Route::get('/logout', [Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::get('change-company/{company}', [Controllers\CompanyController::class, 'setActiveCompany'])->name('change-company')->middleware('auth');

Route::group(['middleware' => ['auth', 'ensure-employee'], 'prefix' => 'employee-portal', 'as' => 'employee-portal.'], function () {
    Route::get('/change-user-information', [Controllers\EmployeePortalController::class, 'changeUserInformation'])->name('change-user-information');
    Route::put('/change-user-information', [Controllers\EmployeePortalController::class, 'updateUserInformation'])->name('change-user-information.update');
    Route::get('/', [Controllers\EmployeePortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/attendance-logs', [Controllers\EmployeePortalController::class, 'attendanceLogs'])->name('attendance-logs');
    Route::get('/monthly-attendances', [Controllers\EmployeePortalController::class, 'monthlyAttendances'])->name('monthly-attendances');
    Route::get('/monthly-attendances/{monthly_attendance}', [Controllers\EmployeePortalController::class, 'monthlyAttendanceShow'])->name('monthly-attendances.show');
    Route::get('/payrolls', [Controllers\EmployeePortalController::class, 'payrolls'])->name('payrolls');
    Route::get('/payrolls/{payroll}', [Controllers\EmployeePortalController::class, 'payrollShow'])->name('payrolls.show');
    Route::get('/personnel-requests', [Controllers\EmployeePortalController::class, 'personnelRequests'])->name('personnel-requests.index');
    Route::get('/personnel-requests/create', [Controllers\EmployeePortalController::class, 'createPersonnelRequest'])->name('personnel-requests.create');
    Route::post('/personnel-requests', [Controllers\EmployeePortalController::class, 'storePersonnelRequest'])->name('personnel-requests.store');
    Route::get('/personnel-requests/{personnel_request}/edit', [Controllers\EmployeePortalController::class, 'editPersonnelRequest'])->name('personnel-requests.edit');
    Route::put('/personnel-requests/{personnel_request}', [Controllers\EmployeePortalController::class, 'updatePersonnelRequest'])->name('personnel-requests.update');
    Route::delete('/personnel-requests/{personnel_request}', [Controllers\EmployeePortalController::class, 'destroyPersonnelRequest'])->name('personnel-requests.destroy');
});

Route::group(['middleware' => ['auth', 'check-permission']], function () {
    Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');
    Route::post('/seed-demo-data', [Controllers\HomeController::class, 'seedDemoData'])->name('home.seed-demo-data');
    Route::get('/home/cash-banks', [Controllers\HomeController::class, 'cashAndBanksBalances'])->name('home.cash-banks');
    Route::get('/home/bank-account', [Controllers\HomeController::class, 'bankAccount'])->name('home.bank-account');
    Route::get('subjects/search', [Controllers\SubjectController::class, 'search'])->name('subjects.search');
    Route::get('subjects/search-code', [Controllers\SubjectController::class, 'searchCode'])->name('subjects.search-code');
    Route::resource('subjects', Controllers\SubjectController::class);
    Route::post('documents/{document}/change-status', [Controllers\DocumentController::class, 'changeStatus'])->name('documents.change-status')->middleware('can:documents.approve');
    Route::post('documents/approve-all', [Controllers\DocumentController::class, 'approveAll'])->name('documents.approve-all')->middleware('can:documents.approve');
    Route::get('documents/search-account-balance', [Controllers\DocumentController::class, 'searchAccountBalance'])->name('documents.search-account-balance');
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
    Route::post('companies/close-fiscal-year/{company}', [Controllers\CompanyController::class, 'closeFiscalYear'])->name('companies.close-fiscal-year')->middleware('can:companies.close-fiscal-year');
    Route::get('companies/{company}/closing-wizard', [Controllers\CompanyController::class, 'closingWizard'])->name('companies.closing-wizard')->middleware('can:companies.close-fiscal-year');
    Route::post('companies/{company}/closing-wizard/step1', [Controllers\CompanyController::class, 'closingWizardStep1'])->name('companies.closing-wizard.step1')->middleware('can:companies.close-fiscal-year');
    Route::post('companies/{company}/closing-wizard/step3', [Controllers\CompanyController::class, 'closingWizardStep3'])->name('companies.closing-wizard.step3')->middleware('can:companies.close-fiscal-year');
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
        Route::post('ancillary-costs/{ancillary_cost}/change-status/{status}', [Controllers\AncillaryCostController::class, 'changeStatus'])->name('ancillary-costs.change-status')->middleware('can:ancillary-costs.approve');
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
    Route::post('invoices/{invoice}/change-status/{status}', [Controllers\InvoiceController::class, 'changeStatus'])->name('invoices.change-status')->middleware('can:invoices.approve');
    Route::group(['prefix' => 'management'], function () {
        Route::post('users/{user}/create-employee', [Controllers\Management\UserController::class, 'createEmployee'])
            ->name('users.create-employee');
        Route::resource('users', Controllers\Management\UserController::class);
        Route::resource('permissions', Controllers\Management\PermissionController::class)->except(['show']);
        Route::resource('roles', Controllers\Management\RoleController::class)->except(['show']);
        Route::resource('configs', Controllers\ConfigController::class);
    });

    Route::group(['prefix' => 'hr', 'as' => 'hr.'], function () {
        Route::resource('employees', Controllers\EmployeeController::class);
        Route::resource('org-charts', Controllers\OrgChartController::class);
        Route::resource('personnel-requests', Controllers\PersonnelRequestController::class);
        Route::patch('personnel-requests/{personnel_request}/approve', [Controllers\PersonnelRequestController::class, 'approve'])->name('personnel-requests.approve');
        Route::patch('personnel-requests/{personnel_request}/reject', [Controllers\PersonnelRequestController::class, 'reject'])->name('personnel-requests.reject');
    });

    Route::group(['prefix' => 'attendance', 'as' => 'attendance.'], function () {
        Route::resource('attendance-logs', Controllers\AttendanceLogController::class)->except(['show']);
        Route::get('attendance-logs/{attendance_log}', [Controllers\AttendanceLogController::class, 'show'])->name('attendance-logs.show');
        Route::post('attendance-logs/{attendance_log}/recalculate', [Controllers\AttendanceLogController::class, 'recalculate'])->name('attendance-logs.recalculate');
        Route::get('attendance-logs-import', [Controllers\AttendanceLogController::class, 'importForm'])->name('attendance-logs.import');
        Route::post('attendance-logs-import/preview', [Controllers\AttendanceLogController::class, 'importPreview'])->name('attendance-logs.import.preview');
        Route::post('attendance-logs-import', [Controllers\AttendanceLogController::class, 'importStore'])->name('attendance-logs.import.store');
        Route::post('monthly-attendances/{monthly_attendance}/attendance-logs/recalculate', [Controllers\AttendanceLogController::class, 'recalculateAll'])->name('attendance-logs.recalculate-all');
        Route::post('monthly-attendances/{monthly_attendance}/recalculate', [Controllers\MonthlyAttendanceController::class, 'recalculate'])->name('monthly-attendances.recalculate');
        Route::post('monthly-attendances/{monthly_attendance}/payroll', [Controllers\PayrollController::class, 'store'])->name('monthly-attendances.payroll.store');
        Route::resource('monthly-attendances', Controllers\MonthlyAttendanceController::class);
        Route::resource('work-shifts', Controllers\WorkShiftController::class)->except(['show']);
    });

    Route::group(['prefix' => 'salary', 'as' => 'salary.'], function () {
        Route::resource('tax-slabs', Controllers\TaxSlabController::class);
        Route::resource('work-sites', Controllers\WorkSiteController::class)->except(['show']);
        Route::resource('work-site-contracts', Controllers\WorkSiteContractController::class)->except(['show']);
        Route::resource('public-holidays', Controllers\PublicHolidayController::class)->except(['show']);
        Route::resource('payroll-elements', Controllers\PayrollElementController::class)->except(['show']);
        Route::resource('salary-decrees', Controllers\SalaryDecreeController::class)->except(['show']);
        Route::get('payrolls/{payroll}', [Controllers\PayrollController::class, 'show'])->name('payrolls.show');
        Route::delete('payrolls/{payroll}', [Controllers\PayrollController::class, 'destroy'])->name('payrolls.destroy');
        Route::get('payroll-items/{payroll_item}/edit', [Controllers\PayrollController::class, 'editItem'])->name('payroll-items.edit');
        Route::put('payroll-items/{payroll_item}', [Controllers\PayrollController::class, 'updateItem'])->name('payroll-items.update');
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
