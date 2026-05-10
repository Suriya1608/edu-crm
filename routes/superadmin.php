<?php

use App\Http\Controllers\Auth\SuperAdminSessionController;
use App\Http\Controllers\SuperAdmin\TenantController;
use Illuminate\Support\Facades\Route;

// Super-admin login (unauthenticated)
Route::middleware('guest:superadmin')->group(function () {
    Route::get('/login',  [SuperAdminSessionController::class, 'create'])->name('superadmin.login');
    Route::post('/login', [SuperAdminSessionController::class, 'store'])->name('superadmin.login.store');
});

// Super-admin authenticated routes
Route::middleware('auth:superadmin')->group(function () {
    Route::post('/logout', [SuperAdminSessionController::class, 'destroy'])->name('superadmin.logout');

    Route::get('/', fn () => redirect()->route('superadmin.tenants.index'));

    Route::prefix('tenants')->name('superadmin.tenants.')->group(function () {
        Route::get('/',                   [TenantController::class, 'index'])->name('index');
        Route::get('/create',             [TenantController::class, 'create'])->name('create');
        Route::post('/',                  [TenantController::class, 'store'])->name('store');
        Route::get('/{tenant}/edit',      [TenantController::class, 'edit'])->name('edit');
        Route::put('/{tenant}',           [TenantController::class, 'update'])->name('update');
        Route::delete('/{tenant}',        [TenantController::class, 'destroy'])->name('destroy');
        Route::post('/{tenant}/provision',[TenantController::class, 'provision'])->name('provision');
        Route::post('/{tenant}/migrate',  [TenantController::class, 'migrate'])->name('migrate');
        Route::patch('/{tenant}/toggle',  [TenantController::class, 'toggle'])->name('toggle');
    });
});
