<?php

use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AttendanceLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\SubjectController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TrialBalanceController;
use App\Http\Controllers\Api\FiscalYearController;
use App\Http\Controllers\Api\ConfigController;

// ========== احراز هویت ==========
Route::post('login', [AuthController::class, 'login'])
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('api.logout');

    Route::get('companies', [CompanyController::class, 'index'])
        ->middleware('check-permission:companies.index')
        ->name('api.companies.index');
});

// ========== Routes مربوط به یک شرکت ==========
Route::prefix('companies/{company}')->middleware(['auth:sanctum', 'api-company'])->group(function () {
    // Services
    Route::get('services', [ServiceController::class, 'index'])
        ->middleware('check-permission:services.index')
        ->name('api.services.index');
    Route::post('services', [ServiceController::class, 'store'])
        ->middleware('check-permission:services.store')
        ->name('api.services.store');
    // Reports
    Route::get('reports/trial-balance', TrialBalanceController::class)
        ->middleware('check-permission:reports.trial-balance')
        ->name('api.reports.trial-balance');
    // Customers
    Route::get('customers', [CustomerController::class, 'index'])
        ->middleware('check-permission:customers.index')
        ->name('api.customers.index');
    Route::get('customers/{id}', [CustomerController::class, 'show'])
    ->middleware('check-permission:customers.show')
    ->name('api.customers.show');
    Route::post('customers', [CustomerController::class, 'store'])
    ->middleware('check-permission:customers.store')
    ->name('api.customers.store');
    // Attendance
    Route::post('attendance/logs', [AttendanceLogController::class, 'store'])
        ->middleware('check-permission:attendance.attendance-logs.store')
        ->name('api.attendance-logs.store');
    Route::get('attendance/logs', [AttendanceLogController::class, 'index'])
        ->middleware('check-permission:attendance.attendance-logs.index')
        ->name('api.attendance-logs.index');

    // Employees
    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('check-permission:hr.employees.index')
        ->name('api.employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('check-permission:hr.employees.store')
        ->name('api.employees.store');

    // Documents
    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('check-permission:documents.store')
        ->name('api.documents.store');
    Route::get('documents/{documentId}', [DocumentController::class, 'show'])
        ->middleware('check-permission:documents.show')
        ->name('api.documents.show');
    Route::post('documents/{documentId}/files', [DocumentController::class, 'attachFile'])
        ->middleware('check-permission:documents.files.store')
        ->name('api.documents.files.store');
    Route::post('documents/{documentId}/status/{status}', [DocumentController::class, 'changeStatus'])
        ->middleware('check-permission:documents.approve')
        ->name('api.documents.change-status');
    Route::get('documents', [DocumentController::class, 'index'])
        ->middleware('check-permission:documents.index')
        ->name('api.documents.index');

    // Subjects
    Route::get('subjects', [SubjectController::class, 'index'])
        ->middleware('check-permission:subjects.index')
        ->name('api.subjects.index');

    Route::get('subjects/{id}', [SubjectController::class, 'show'])
        ->middleware('check-permission:subjects.show')
        ->name('api.subjects.show');

    // Products
    Route::get('products', [ProductController::class, 'index'])
        ->middleware('check-permission:products.index')
        ->name('api.products.index');
    Route::post('products', [ProductController::class, 'store'])
        ->middleware('check-permission:products.store')
        ->name('api.products.store');


    // Fiscal years
    Route::get('fiscal-years', [FiscalYearController::class, 'index'])
        ->middleware('check-permission:companies.index')
        ->name('api.fiscal-years.index');

    // Company config
    Route::get('config', [ConfigController::class, 'index'])
        ->middleware('check-permission:configs.index')
        ->name('api.config.index');

    // Invoices
    Route::get('invoices', [InvoiceController::class, 'index'])
        ->middleware('check-permission:invoices.index')
        ->name('api.invoices.index');

    Route::get('invoices/{id}', [InvoiceController::class, 'show'])
        ->middleware('check-permission:invoices.show')
        ->whereNumber('id')
        ->name('api.invoices.show');

    Route::post('invoices', [InvoiceController::class, 'store'])
        ->middleware('check-permission:invoices.store')
        ->name('api.invoices.store');

    Route::post('invoices/{invoice}/change-status/{status}', [InvoiceController::class, 'changeStatus'])
        ->middleware('check-permission:invoices.change-status')
        ->whereNumber('invoice')
        ->name('api.invoices.change-status');
});
