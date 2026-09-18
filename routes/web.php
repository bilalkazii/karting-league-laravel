<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/groups', [GroupController::class, 'index'])->name('groups');
    Route::get('/groups/new', [GroupController::class, 'create'])->name('groups.new');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/members', [GroupController::class, 'members'])->name('groups.members');
    Route::post('/groups/{group}/availability', [GroupController::class, 'updateAvailability'])->name('groups.availability.update');

    Route::view('/races', 'feature-placeholder')->name('races');
    Route::view('/championship', 'feature-placeholder')->name('championship');
    Route::view('/chat', 'feature-placeholder')->name('chat');
    Route::view('/notifications', 'feature-placeholder')->name('notifications');
    Route::view('/settings', 'feature-placeholder')->name('settings');
    Route::view('/race-setup', 'feature-placeholder')->name('race-setup');

    Route::get('/profile', function () {
        $driver = auth()->user()?->driver;

        return $driver
            ? redirect()->route('drivers.show', $driver)
            : redirect()->route('dashboard');
    })->name('profile');
    Route::get('/drivers', [DriverController::class, 'index'])->name('drivers');
    Route::get('/drivers/{driver}', [DriverController::class, 'show'])->name('drivers.show');
    Route::get('/drivers/{driver}/edit', [DriverController::class, 'edit'])->name('drivers.edit');
    Route::patch('/drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');

    Route::get('/races/new', fn () => abort(501))->name('races.new');
    Route::get('/races/{race}', fn () => abort(501))->name('races.show');

    Route::get('/account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/account', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/account', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
