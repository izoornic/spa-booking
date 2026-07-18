<?php

use App\Http\Controllers\OwnerAccessController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

/*
 * Owner (vlasnik) access — token-based, no password.
 * The unique per-owner token (from a QR link) starts an owner session.
 */
Route::get('/pristup/{vlasnik:token}', OwnerAccessController::class)->name('spa.access');

Route::middleware('owner')->group(function () {
    Route::livewire('/spa', 'pages::spa.home')->name('spa.home');
});

require __DIR__.'/settings.php';
