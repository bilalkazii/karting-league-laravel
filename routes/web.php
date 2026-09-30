<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RaceController;
use App\Http\Controllers\RaceSessionController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SettingsController;
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
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.store');
    Route::patch('/groups/{group}/members/{driver}/role', [GroupController::class, 'updateMemberRole'])->name('groups.members.role');
    Route::delete('/groups/{group}/members/{driver}', [GroupController::class, 'removeMember'])->name('groups.members.destroy');
    Route::post('/groups/{group}/availability', [GroupController::class, 'updateAvailability'])->name('groups.availability.update');

    Route::post('/groups/{group}/invites', [InviteController::class, 'store'])
        ->middleware('throttle:invite-create')
        ->name('groups.invites.store');
    Route::delete('/groups/{group}/invites/{invite}', [InviteController::class, 'destroy'])
        ->name('groups.invites.destroy');
    Route::post('/groups/{group}/invites/{invite}/regenerate', [InviteController::class, 'regenerate'])
        ->name('groups.invites.regenerate');

    Route::get('/races', [RaceController::class, 'index'])->name('races');
    Route::get('/races/new', [RaceController::class, 'create'])->name('races.new');
    Route::post('/races', [RaceController::class, 'store'])->name('races.store');
    Route::get('/races/{race}', [RaceController::class, 'show'])->name('races.show');
    Route::get('/races/{race}/edit', [RaceController::class, 'edit'])->name('races.edit');
    Route::patch('/races/{race}', [RaceController::class, 'update'])->name('races.update');
    Route::delete('/races/{race}', [RaceController::class, 'destroy'])->name('races.destroy');

    Route::post('/races/{race}/lobby', [RaceSessionController::class, 'openLobby'])->name('races.lobby.open');
    Route::post('/races/{race}/qualifying/start', [RaceSessionController::class, 'startQualifying'])->name('races.qualifying.start');
    Route::post('/races/{race}/qualifying', [RaceSessionController::class, 'recordQualifying'])->name('races.qualifying.record');
    Route::post('/races/{race}/lock-grid', [RaceSessionController::class, 'lockGrid'])->name('races.lock-grid');
    Route::post('/races/{race}/entries', [RaceSessionController::class, 'setParticipants'])->name('races.entries.set');
    Route::post('/races/{race}/entries/{driver}', [RaceSessionController::class, 'updateEntry'])->name('races.entries.update');
    Route::post('/races/{race}/entries/{driver}/ready', [RaceSessionController::class, 'toggleReady'])->name('races.entries.ready');
    Route::delete('/races/{race}/entries/{driver}', [RaceSessionController::class, 'removeEntry'])->name('races.entries.remove');
    Route::post('/races/{race}/drivers/{driver}/status', [RaceSessionController::class, 'setDriverStatus'])->name('races.driver-status');
    Route::post('/races/{race}/start', [RaceSessionController::class, 'startRace'])->name('races.start');
    Route::post('/races/{race}/complete', [RaceSessionController::class, 'completeRace'])->name('races.complete');
    Route::post('/races/{race}/cancel', [RaceSessionController::class, 'cancelRace'])->name('races.cancel');
    Route::post('/races/{race}/penalties', [RaceSessionController::class, 'issuePenalty'])->name('races.penalties.store');
    Route::post('/races/{race}/penalties/{penalty}/cancel', [RaceSessionController::class, 'cancelPenalty'])->name('races.penalties.cancel');

    Route::get('/championship', [SeasonController::class, 'index'])->name('championship');
    Route::get('/championship/{season}', [SeasonController::class, 'show'])->name('championship.show');
    Route::get('/seasons', [SeasonController::class, 'index'])->name('seasons');
    Route::get('/seasons/new', [SeasonController::class, 'create'])->name('seasons.new');
    Route::post('/seasons', [SeasonController::class, 'store'])->name('seasons.store');
    Route::get('/seasons/{season}', [SeasonController::class, 'show'])->name('seasons.show');
    Route::get('/seasons/{season}/edit', [SeasonController::class, 'edit'])->name('seasons.edit');
    Route::patch('/seasons/{season}', [SeasonController::class, 'update'])->name('seasons.update');
    Route::delete('/seasons/{season}', [SeasonController::class, 'destroy'])->name('seasons.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/chat/groups/{group}', [ChatController::class, 'group'])->name('chat.group');
    Route::post('/chat/groups/{group}', [ChatController::class, 'storeGroup'])->name('chat.group.send');
    Route::get('/chat/races/{race}', [ChatController::class, 'race'])->name('chat.race');
    Route::post('/chat/races/{race}', [ChatController::class, 'storeRace'])->name('chat.race.send');
    Route::delete('/chat/messages/{message}', [ChatController::class, 'destroy'])->name('chat.messages.destroy');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
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

    Route::get('/account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/account', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/account', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/invites/{token}/accept', [InviteController::class, 'accept'])
        ->middleware('throttle:invite-accept')
        ->name('invites.accept');
});

Route::get('/invites/{token}', [InviteController::class, 'show'])
    ->middleware('throttle:invite-show')
    ->name('invites.show');

require __DIR__.'/auth.php';
