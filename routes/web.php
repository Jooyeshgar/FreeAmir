<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::resource('subjects', Controllers\SubjectController::class);
Route::resource('transactions', Controllers\TransactionController::class);
Route::resource('products', Controllers\ProductController::class);
Route::resource('customers', Controllers\CustomerController::class);
Route::resource('customer-groups', Controllers\CustomerGroupController::class);
