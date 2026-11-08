<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Finance'])->prefix('admin/finance')->name('admin.finance.')->group(function () {
    Route::get('/', [\Modules\Finance\Http\Controllers\FinanceController::class, 'index'])->name('index')->middleware('can:finance.dashboard.view');
    Route::post('prices', [\Modules\Finance\Http\Controllers\FinanceController::class, 'storePrice'])->name('prices.store')->middleware('can:finance.pricing.manage');
    Route::post('prices/{price}/transition', [\Modules\Finance\Http\Controllers\FinanceController::class, 'transitionPrice'])->name('prices.transition')->middleware('can:finance.pricing.approve');
    Route::post('charges', [\Modules\Finance\Http\Controllers\FinanceController::class, 'storeCharge'])->name('charges.store')->middleware('can:finance.charges.manage');
    Route::post('charges/{charge}/transition', [\Modules\Finance\Http\Controllers\FinanceController::class, 'transitionCharge'])->name('charges.transition');
    Route::post('charges/{charge}/reverse', [\Modules\Finance\Http\Controllers\FinanceController::class, 'reverseCharge'])->name('charges.reverse')->middleware('can:finance.charges.reverse');
    Route::get('patients/{patient}/summary', [\Modules\Finance\Http\Controllers\FinanceController::class, 'patientSummary'])->name('patient.summary')->middleware('can:finance.patient_financials.view');
});
