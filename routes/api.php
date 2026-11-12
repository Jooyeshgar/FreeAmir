<?php

use App\Http\Controllers\Api\AttendanceLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('companies', [CompanyController::class, 'index'])
        ->middleware('check-permission:companies.index')
        ->name('api.companies.index');
});

Route::prefix('companies/{fiscal_year}')
    ->middleware(['auth:sanctum', 'api-fiscal-year'])
    ->group(function () {
        Route::get('attendance/logs', [AttendanceLogController::class, 'index'])
            ->middleware('check-permission:attendance.attendance-logs.index')
            ->name('api.attendance-logs.index');
        Route::post('attendance/logs', [AttendanceLogController::class, 'store'])
            ->middleware(['check-permission:attendance.attendance-logs.store', 'fiscal-year-open'])
            ->name('api.attendance-logs.store');

        Route::get('employees', [EmployeeController::class, 'index'])
            ->middleware('check-permission:hr.employees.index')
            ->name('api.employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])
            ->middleware(['check-permission:hr.employees.store', 'fiscal-year-open'])
            ->name('api.employees.store');

        Route::get('documents', [DocumentController::class, 'index'])
            ->middleware('check-permission:documents.index')
            ->name('api.documents.index');
        Route::get('documents/{documentId}', [DocumentController::class, 'show'])
            ->middleware('check-permission:documents.show')
            ->name('api.documents.show');
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware(['check-permission:documents.store', 'fiscal-year-open'])
            ->name('api.documents.store');
        Route::post('documents/{documentId}/files', [DocumentController::class, 'attachFile'])
            ->middleware(['check-permission:documents.files.store', 'fiscal-year-open'])
            ->name('api.documents.files.store');
        Route::post('documents/{documentId}/status/{status}', [DocumentController::class, 'changeStatus'])
            ->middleware(['check-permission:documents.approve', 'fiscal-year-open'])
            ->name('api.documents.change-status');

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('check-permission:customers.index')
            ->name('api.customers.index');
        Route::get('customers/{id}', [CustomerController::class, 'show'])
            ->middleware('check-permission:customers.show')
            ->name('api.customers.show');
        Route::post('customers', [CustomerController::class, 'store'])
            ->middleware(['check-permission:customers.store', 'fiscal-year-open'])
            ->name('api.customers.store');

        Route::get('products', [ProductController::class, 'index'])
            ->middleware('check-permission:products.index')
            ->name('api.products.index');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware(['check-permission:products.store', 'fiscal-year-open'])
            ->name('api.products.store');

        Route::get('services', [ServiceController::class, 'index'])
            ->middleware('check-permission:services.index')
            ->name('api.services.index');
        Route::post('services', [ServiceController::class, 'store'])
            ->middleware(['check-permission:services.store', 'fiscal-year-open'])
            ->name('api.services.store');

        Route::get('subjects', [SubjectController::class, 'index'])
            ->middleware('check-permission:subjects.index')
            ->name('api.subjects.index');
        Route::get('subjects/{id}', [SubjectController::class, 'show'])
            ->middleware('check-permission:subjects.show')
            ->name('api.subjects.show');

        Route::get('config', [ConfigController::class, 'index'])
            ->middleware('check-permission:configs.index')
            ->name('api.config.index');
        Route::get('reports/trial-balance', TrialBalanceController::class)
            ->middleware('check-permission:reports.trial-balance')
            ->name('api.reports.trial-balance');

        Route::get('invoices', [InvoiceController::class, 'index'])
            ->middleware('check-permission:invoices.index')
            ->name('api.invoices.index');
        Route::get('invoices/{id}', [InvoiceController::class, 'show'])
            ->whereNumber('id')
            ->middleware('check-permission:invoices.show')
            ->name('api.invoices.show');
        Route::post('invoices', [InvoiceController::class, 'store'])
            ->middleware(['check-permission:invoices.store', 'fiscal-year-open'])
            ->name('api.invoices.store');
        Route::post('invoices/{invoice}/change-status/{status}', [InvoiceController::class, 'changeStatus'])
            ->whereNumber('invoice')
            ->middleware(['check-permission:invoices.change-status', 'fiscal-year-open'])
            ->name('api.invoices.change-status');
    });
