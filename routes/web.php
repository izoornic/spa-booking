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

/*
 * Attendant (domar) desk — authenticated staff. Occupancy overview and
 * QR/short-code reservation check-in.
 */
Route::middleware(['auth', 'attendant'])->group(function () {
    Route::livewire('/domar', 'pages::domar.home')->name('domar.home');
    Route::livewire('/domar/rezervacija/{kod}', 'pages::domar.rezervacija')->name('domar.rezervacija');
});

/*
 * Manager (upravnik) panel — authenticated. Reservations overview, slot
 * blockades and spa configuration.
 */
Route::middleware(['auth', 'manager'])->prefix('upravnik')->name('upravnik.')->group(function () {
    Route::livewire('/rezervacije', 'pages::upravnik.rezervacije')->name('rezervacije');
    Route::livewire('/blokade', 'pages::upravnik.blokade')->name('blokade');
    Route::livewire('/konfiguracija', 'pages::upravnik.konfiguracija')->name('konfiguracija');
    Route::livewire('/qr-vlasnici', 'pages::upravnik.qr-vlasnici')->name('qr-vlasnici');
});

require __DIR__.'/settings.php';
