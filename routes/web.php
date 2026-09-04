<?php

use App\Http\Controllers\Auth\AzureLoginController;
use App\Http\Controllers\SolutionsListPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('register.index'));

// --- Microsoft 365 SSO (no local password login) ---------------------
Route::get('/login', fn () => view('auth.login'))->name('login');
Route::get('/auth/azure/redirect', [AzureLoginController::class, 'redirect'])->name('auth.azure.redirect');
Route::get('/auth/azure/callback', [AzureLoginController::class, 'callback'])->name('auth.azure.callback');
Route::post('/logout', [AzureLoginController::class, 'logout'])->name('logout');

// --- Register / checkout (Microsoft 365 sign-in required) -------------
Route::middleware('auth')->group(function () {
    Route::get('/register', fn () => view('register.index'))->name('register.index');

    Route::get('/solutions-lists/{solutionsList}/print', SolutionsListPrintController::class)
        ->name('solutions-lists.print');
});
