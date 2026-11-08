<?php

use Illuminate\Support\Facades\Route;

// Foundation API routes (for future module integrations).
Route::middleware('auth:sanctum')->prefix('foundation')->name('api.foundation.')->group(function () {
    //
});
