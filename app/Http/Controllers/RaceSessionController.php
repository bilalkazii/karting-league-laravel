<?php

namespace App\Http\Controllers;

use App\Enums\RaceDriverStatus;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\RacePenalty;
use App\Services\RaceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Live race-session actions: entries, qualifying, grid lock, start/complete,
 * driver statuses, penalties and cancellation. All require group manage rights.
 */
class RaceSessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly RaceService $races) {}

    public function openLobby(Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->openLobby($race);

        return back();
    }

    public function startQualifying(Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->startQualifying($race);

        return back();
    }

    public function setParticipants(Request $request, Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);

        $validated = $request->validate(
            [
                'driver_ids' => ['array'],
                'driver_ids.*' => [
                    'integer',
                    'distinct',
                    function (string $attribute, mixed $value, \Closure $fail) use ($race): void {
                        if (! Group::findOrFail($race->group_id)->members()->whereKey($value)->exists()) {
                            $fail('Only group members can be added to the field.');
                        }
                    },
                ],
            ]
        );

        $this->races->setParticipants($race, $validated['driver_ids'] ?? []);

        $race->events()->create([
            'driver_id' => null,
            'type' => 'note',
            'occurred_at' => now(),
            'payload' => ['field' => count($validated['driver_ids'] ?? [])],
        ]);

        return back();
    }

    public function updateEntry(Request $request, Race $race, int $driver): RedirectResponse
    {
        $this->authorize('manage', $race);

        $validated = $request->validate([
            'kart_number' => ['nullable', 'integer', 'min:0', 'max:99'],
            'confirmed' => ['nullable', 'boolean'],
            'grid_penalty' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        if (array_key_exists('kart_number', $validated)) {
            $this->races->updateKart($race, $driver, $validated['kart_number']);
        }
        if (array_key_exists('confirmed', $validated)) {
            $this->races->toggleConfirmed($race, $driver);
        }
        if (array_key_exists('grid_penalty', $validated)) {
            $this->races->setGridPenalty($race, $driver, (int) $validated['grid_penalty']);
        }

        return back();
    }

    public function toggleReady(Race $race, int $driver): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->toggleReady($race, $driver);

        return back();
    }

    public function removeEntry(Race $race, int $driver): RedirectResponse
    {
        $this->authorize('manage', $race);
        $race->entries()->where('driver_id', $driver)->delete();

        return back();
    }

    public function setDriverStatus(Request $request, Race $race, int $driver): RedirectResponse
    {
        $this->authorize('manage', $race);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                RaceDriverStatus::Finished->value,
                RaceDriverStatus::Dnf->value,
                RaceDriverStatus::Dns->value,
                RaceDriverStatus::Retired->value,
                RaceDriverStatus::Withdrawn->value,
            ])],
        ]);

        $this->races->setDriverStatus($race, $driver, $validated['status']);

        return back();
    }

    public function recordQualifying(Request $request, Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer'],
            'action' => ['required', 'in:record,manual,invalidate,restore,clear'],
            'time_ms' => ['nullable', 'integer', 'min:1'],
        ]);

        $driverId = (int) $validated['driver_id'];
        $manual = $request->boolean('manual') && $validated['action'] === 'record';
        $action = $manual ? 'manual' : $validated['action'];
        $timeMs = $this->resolveTimeMs($request);

        match ($action) {
            'record' => $this->races->recordQualifyingTime($race, $driverId, $timeMs),
            'manual' => $this->races->manualCorrectQualifying($race, $driverId, $timeMs),
            'invalidate' => $this->races->invalidateQualifying($race, $driverId),
            'restore' => $this->races->restoreQualifying($race, $driverId),
            'clear' => $this->races->clearQualifying($race, $driverId),
            default => null,
        };

        return back();
    }

    private function resolveTimeMs(Request $request): int
    {
        if ($request->filled('time_ms')) {
            return (int) $request->input('time_ms');
        }

        $minutes = (int) $request->input('split_min', 0);
        $seconds = (float) $request->input('split_sec', 0);
        $millis = (int) $request->input('split_ms', 0);

        return (int) ($minutes * 60000 + $seconds * 1000 + $millis);
    }

    public function lockGrid(Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->lockGrid($race);

        return back();
    }

    public function startRace(Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->startRace($race);

        return back();
    }

    public function completeRace(Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->completeRace($race);

        return back();
    }

    public function cancelRace(Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->cancelRace($race);

        return back();
    }

    public function issuePenalty(Request $request, Race $race): RedirectResponse
    {
        $this->authorize('manage', $race);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer'],
            'seconds' => ['required', 'integer', 'min:1', 'max:99'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->races->issuePenalty(
            $race,
            (int) $validated['driver_id'],
            (int) $validated['seconds'],
            $validated['reason'],
            $request->user()->driver?->id
        );

        return back();
    }

    public function cancelPenalty(Race $race, RacePenalty $penalty): RedirectResponse
    {
        $this->authorize('manage', $race);
        $this->races->cancelPenalty($race, $penalty);

        return back();
    }
}
