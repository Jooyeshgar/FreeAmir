<?php

use App\Http\Controllers\Api\AttendanceLogController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'api-company'])->group(function () {
    Route::post('attendance/logs', [AttendanceLogController::class, 'store'])
        ->middleware('check-permission:attendance.attendance-logs.store')
        ->name('api.attendance-logs.store');
    Route::get('attendance/logs', [AttendanceLogController::class, 'index'])
        ->middleware('check-permission:attendance.attendance-logs.index')
        ->name('api.attendance-logs.index');

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('check-permission:hr.employees.index')
        ->name('api.employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('check-permission:hr.employees.store')
        ->name('api.employees.store');

    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('check-permission:documents.store')
        ->name('api.documents.store');
    Route::get('documents/{documentId}', [DocumentController::class, 'show'])
        ->middleware('check-permission:documents.show')
        ->name('api.documents.show');
    Route::post('documents/{documentId}/files', [DocumentController::class, 'attachFile'])
        ->middleware('check-permission:documents.files.store')
        ->name('api.documents.files.store');
});
