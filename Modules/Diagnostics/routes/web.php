<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['web', 'auth'])->prefix('admin/diagnostics')->name('admin.diagnostics.')->group(function () {
    Route::post('tests', [\Modules\Diagnostics\Http\Controllers\DiagnosticsController::class, 'storeTest'])->name('tests.store');
});
