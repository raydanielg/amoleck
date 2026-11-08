<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['web', 'auth'])->prefix('admin/inventory')->name('admin.inventory.')->group(function () {
    Route::post('receipts', [\Modules\Inventory\Http\Controllers\InventoryController::class, 'storeReceipt'])->name('receipts.store');
    Route::post('issues', [\Modules\Inventory\Http\Controllers\InventoryController::class, 'storeIssue'])->name('issues.store');
    Route::post('transfers', [\Modules\Inventory\Http\Controllers\InventoryController::class, 'storeTransfer'])->name('transfers.store');
});
