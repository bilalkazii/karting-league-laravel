<?php

namespace App\Http\Controllers;

use App\Enums\RaceStatus;
use App\Enums\SeasonStatus;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\RaceEvent;
use App\Models\Season;
use Illuminate\Support\Collection;
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
                'activity' => [],
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
            'activity' => $this->recentActivity($groupIds),
        ]);
    }

    /**
     * Bounded, group-scoped feed drawn from real race events. Returns a
     * presentation-ready shape for the dashboard activity card.
     *
     * @return list<array{name: string, time: string, initials: string, color: string, textColor: string}>
     */
    private function recentActivity(Collection $groupIds): array
    {
        if ($groupIds->isEmpty()) {
            return [];
        }

        return RaceEvent::query()
            ->with(['race.group', 'driver.profile'])
            ->whereHas('race', fn ($query) => $query->whereIn('group_id', $groupIds))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (RaceEvent $event): array {
                $race = $event->race;
                $driver = $event->driver;
                $raceName = $race?->name ?? 'Race';
                $label = $event->type?->label() ?? 'Race update';

                return [
                    'name' => $label.' · '.$raceName,
                    'time' => $event->occurred_at?->diffForHumans() ?? 'Recently',
                    'initials' => $driver?->nickname ?: mb_strtoupper(mb_substr($raceName, 0, 2)),
                    'color' => $driver?->avatar_color ?? $race?->group?->logo_color ?? '#3f3f46',
                    'textColor' => $driver?->avatar_text_color ?? $race?->group?->logo_text_color ?? '#ffffff',
                ];
            })
            ->all();
    }
}
