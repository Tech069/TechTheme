<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\VexyThemes\UpdateController;

Route::prefix('api/v2/vexythemes')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/update/check', [UpdateController::class, 'check']);
    Route::get('/update/version', [UpdateController::class, 'currentVersion']);
    Route::post('/update/apply', [UpdateController::class, 'update']);
    Route::post('/update/restore', [UpdateController::class, 'restore']);
    Route::get('/update/backups', [UpdateController::class, 'backups']);
    Route::post('/update/clear-cache', function () {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('view:clear');
        return response()->json(['success' => true]);
    });
});
