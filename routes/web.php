<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/login', [Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [Controllers\Auth\LoginController::class, 'login']);
Route::get('/logout', [Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');
    Route::resource('subjects', Controllers\SubjectController::class);
    Route::resource('transactions', Controllers\DocumentController::class);
    Route::resource('products', Controllers\ProductController::class);
    Route::resource('product-groups', Controllers\ProductGroupController::class);
    Route::resource('customers', Controllers\CustomerController::class);
    Route::resource('customer-groups', Controllers\CustomerGroupController::class);
    Route::resource('bank-accounts', Controllers\BankAccountController::class);
    Route::resource('banks', Controllers\BankController::class);
    Route::resource('invoices', Controllers\InvoiceController::class);
    Route::group(['prefix' => 'management'], function () {
        Route::resource('users', Controllers\Management\UserController::class);
        Route::resource('permissions', Controllers\Management\PermissionController::class)->except(['show']);
        Route::resource('roles', Controllers\Management\RoleController::class)->except(['show']);
        Route::resource('configs', Controllers\ConfigController::class);
    });
    Route::group(['prefix' => 'reports','as'=>'reports.'], function () {
        Route::get('all', [Controllers\ReportsController::class,'all'])->name('all');
        Route::get('roozname', [Controllers\ReportsController::class,'roozname'])->name('roozname');
        Route::get('moein',[Controllers\ReportsController::class,'moien'] )->name('moein');
        Route::get('result',[Controllers\ReportsController::class,'result'] )->name('result');
    });
});
