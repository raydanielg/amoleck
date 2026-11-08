<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Administration'])->prefix('admin/hr')->name('admin.hr.')->group(function () {
    Route::get('/', [\Modules\Administration\Http\Controllers\HrController::class, 'index'])->name('index')->middleware('can:hr.dashboard.view');
    Route::post('employees', [\Modules\Administration\Http\Controllers\HrController::class, 'store'])->name('employees.store')->middleware('can:hr.employees.manage');
    Route::post('employees/{employee}/transition', [\Modules\Administration\Http\Controllers\HrController::class, 'transition'])->name('employees.transition')->middleware('can:hr.employees.manage');
    Route::post('employees/{employee}/transfer', [\Modules\Administration\Http\Controllers\HrController::class, 'transfer'])->name('employees.transfer')->middleware('can:hr.employees.transfer');
    Route::post('employees/{employee}/contracts', [\Modules\Administration\Http\Controllers\HrController::class, 'storeContract'])->name('contracts.store')->middleware('can:hr.contracts.manage');
    Route::post('positions', [\Modules\Administration\Http\Controllers\HrController::class, 'storePosition'])->name('positions.store')->middleware('can:hr.positions.manage');
});
