<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::view('/groups', 'feature-placeholder')->name('groups');
    Route::view('/races', 'feature-placeholder')->name('races');
    Route::view('/championship', 'feature-placeholder')->name('championship');
    Route::view('/chat', 'feature-placeholder')->name('chat');
    Route::view('/notifications', 'feature-placeholder')->name('notifications');
    Route::view('/settings', 'feature-placeholder')->name('settings');
    Route::view('/race-setup', 'feature-placeholder')->name('race-setup');

    Route::get('/profile', function () {
        $driver = auth()->user()?->driver;

        return $driver
            ? redirect()->route('profile.show', $driver)
            : redirect()->route('dashboard');
    })->name('profile');
    Route::get('/drivers/{driver}', [DriverController::class, 'show'])->name('profile.show');

    Route::get('/groups/new', fn () => abort(501))->name('groups.new');
    Route::get('/groups/{group}', fn () => abort(501))->name('groups.show');
    Route::get('/races/new', fn () => abort(501))->name('races.new');
    Route::get('/races/{race}', fn () => abort(501))->name('races.show');

    Route::get('/account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/account', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/account', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';