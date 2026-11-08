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

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Finance'])->prefix('admin/payments')->name('admin.payments.')->group(function () {
    Route::get('/', [\Modules\Finance\Http\Controllers\PaymentController::class, 'index'])->name('index')->middleware('can:finance.payments.view');
    Route::post('transactions', [\Modules\Finance\Http\Controllers\PaymentController::class, 'record'])->name('transactions.store')->middleware('can:finance.payments.create');
    Route::post('transactions/{payment}/confirm', [\Modules\Finance\Http\Controllers\PaymentController::class, 'confirm'])->name('confirm')->middleware('can:finance.payments.verify');
    Route::post('transactions/{payment}/fail', [\Modules\Finance\Http\Controllers\PaymentController::class, 'fail'])->name('fail')->middleware('can:finance.payments.verify');
    Route::post('transactions/{payment}/refund', [\Modules\Finance\Http\Controllers\PaymentController::class, 'requestRefund'])->name('refund')->middleware('can:finance.refunds.create');
    Route::post('refunds/{refund}/process', [\Modules\Finance\Http\Controllers\PaymentController::class, 'processRefund'])->name('refunds.process')->middleware('can:finance.refunds.approve');
    Route::post('shifts/open', [\Modules\Finance\Http\Controllers\PaymentController::class, 'openShift'])->name('shifts.open')->middleware('can:finance.cashier.shift.manage');
    Route::post('shifts/{shift}/close', [\Modules\Finance\Http\Controllers\PaymentController::class, 'closeShift'])->name('shifts.close')->middleware('can:finance.cashier.shift.manage');
    Route::get('transactions/{payment}/receipt', [\Modules\Finance\Http\Controllers\PaymentController::class, 'receipt'])->name('receipt')->middleware('can:finance.receipts.generate');
    Route::post('methods', [\Modules\Finance\Http\Controllers\PaymentController::class, 'storeMethod'])->name('methods.store')->middleware('can:finance.payments.create');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Finance'])->prefix('admin/insurance')->name('admin.insurance.')->group(function () {
    Route::get('/', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'index'])->name('index')->middleware('can:finance.insurance.view');
    Route::post('providers', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storeProvider'])->name('providers.store')->middleware('can:finance.insurance.manage');
    Route::post('plans', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storePlan'])->name('plans.store')->middleware('can:finance.insurance.manage');
    Route::post('memberships', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storeMembership'])->name('memberships.store')->middleware('can:finance.insurance.manage');
    Route::post('memberships/{membership}/verify', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'verifyMembership'])->name('memberships.verify')->middleware('can:finance.insurance.verify');
    Route::post('claims', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storeClaim'])->name('claims.store')->middleware('can:finance.insurance.claims.manage');
    Route::post('claims/{claim}/transition', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'transitionClaim'])->name('claims.transition')->middleware('can:finance.insurance.claims.manage');
    Route::post('preauth', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storePreauth'])->name('preauth.store');
    Route::post('preauth/{preauth}/transition', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'transitionPreauth'])->name('preauth.transition')->middleware('can:finance.insurance.claims.manage');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Finance'])->prefix('admin/corporate')->name('admin.corporate.')->group(function () {
    Route::get('/', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'corporateIndex'])->name('index')->middleware('can:finance.corporate.view');
    Route::post('clients', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storeCorporateClient'])->name('clients.store')->middleware('can:finance.corporate.manage');
    Route::post('sponsorships', [\Modules\Finance\Http\Controllers\InsuranceController::class, 'storeSponsorship'])->name('sponsorships.store')->middleware('can:finance.corporate.manage');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Finance'])->prefix('admin/billing')->name('admin.billing.')->group(function () {
    Route::get('/', [\Modules\Finance\Http\Controllers\BillingController::class, 'index'])->name('index')->middleware('can:finance.invoices.view');
    Route::post('invoices', [\Modules\Finance\Http\Controllers\BillingController::class, 'generate'])->name('invoices.generate')->middleware('can:finance.invoices.create');
    Route::get('invoices/{invoice}', [\Modules\Finance\Http\Controllers\BillingController::class, 'show'])->name('show')->middleware('can:finance.invoices.view');
    Route::post('invoices/{invoice}/transition', [\Modules\Finance\Http\Controllers\BillingController::class, 'transition'])->name('transition');
    Route::post('invoices/{invoice}/credit-note', [\Modules\Finance\Http\Controllers\BillingController::class, 'creditNote'])->name('credit-note')->middleware('can:finance.credit_notes.issue');
    Route::get('invoices/{invoice}/print', [\Modules\Finance\Http\Controllers\BillingController::class, 'print'])->name('print')->middleware('can:finance.invoices.download');
    Route::get('patients/{patient}/statement', [\Modules\Finance\Http\Controllers\BillingController::class, 'statement'])->name('statement')->middleware('can:finance.statements.view');
});
