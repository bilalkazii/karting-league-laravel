<?php

namespace App\Http\Controllers;

use App\Enums\RaceStatus;
use App\Enums\SeasonStatus;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\Season;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $driver = Auth::user()?->driver;

        if (! $driver) {
            return view('dashboard', [
                'driver' => null,
                'groups' => [],
                'memberCounts' => [],
                'stats' => ['started' => 0, 'points' => 0, 'bestQuali' => null],
                'upcomingRace' => null,
            ]);
        }

        $groups = $driver->groups()->orderBy('name')->get();
        $groupIds = $groups->pluck('id');

        $memberCounts = $groups->mapWithKeys(fn ($group) => [$group->id => $group->members()->count()]);

        $upcomingRace = Race::whereIn('group_id', $groupIds)
            ->whereIn('status', [RaceStatus::Draft->value, RaceStatus::Lobby->value])
            ->where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        $started = RaceEntry::where('driver_id', $driver->id)
            ->whereNotNull('finish_position')
            ->count();

        $season = Season::whereIn('group_id', $groupIds)
            ->where('status', SeasonStatus::Active->value)
            ->with('scoringPoints')
            ->first();

        $points = 0;
        if ($season) {
            $positionPoints = $season->scoringPoints->pluck('points', 'position');
            $points = RaceEntry::where('driver_id', $driver->id)
                ->whereNotNull('finish_position')
                ->get()
                ->sum(fn ($entry) => $positionPoints[$entry->finish_position] ?? 0);
        }

        $bestQuali = RaceEntry::where('driver_id', $driver->id)
            ->whereNotNull('qualifying_time_ms')
            ->orderBy('qualifying_time_ms')
            ->value('qualifying_time_ms');

        return view('dashboard', [
            'driver' => $driver,
            'groups' => $groups,
            'memberCounts' => $memberCounts,
            'stats' => [
                'started' => $started,
                'points' => $points,
                'bestQuali' => $bestQuali,
            ],
            'upcomingRace' => $upcomingRace,
        ]);
    }
}