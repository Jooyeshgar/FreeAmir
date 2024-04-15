<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;



Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');

Route::resource('subjects', Controllers\SubjectController::class);
Route::resource('transactions', Controllers\TransactionController::class);
Route::resource('products', Controllers\ProductController::class);
Route::resource('product-groups', Controllers\ProductGroupController::class);
Route::resource('customers', Controllers\CustomerController::class);
Route::resource('customer-groups', Controllers\CustomerGroupController::class);
Route::resource('bank-accounts', Controllers\BankAccountController::class);
Route::resource('banks', Controllers\BankController::class);
