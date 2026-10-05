<?php

namespace App\Http\Controllers;

use App\Enums\DriverProfileVisibility;
use App\Enums\RaceDriverStatus;
use App\Enums\RacePenaltyStatus;
use App\Http\Requests\UpdateDriverProfileRequest;
use App\Models\Driver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DriverController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Driver::class);

        $viewer = auth()->user()?->driver;
        $viewerGroupIds = $viewer
            ? $viewer->groups()->pluck('groups.id')->all()
            : [];

        $hiddenDriverIds = Driver::query()
            ->when($viewer, fn ($query) => $query->where('id', '!=', $viewer->id))
            ->whereHas('profile.user.preferences', fn ($preferences) => $preferences
                ->where('key', DriverProfileVisibility::preferenceKey())
                ->where('value', DriverProfileVisibility::Members->value))
            ->whereDoesntHave('groups', fn ($groups) => $groups->whereIn('groups.id', $viewerGroupIds))
            ->pluck('id');

        $drivers = Driver::query()
            ->with('profile')
            ->with(['groups' => fn ($q) => $q->whereIn('groups.id', $viewerGroupIds)])
            ->with(['teams' => fn ($q) => $q->whereIn('teams.group_id', $viewerGroupIds), 'teams.group'])
            ->when(request('q'), function ($query, $term) {
                $query->where(function ($where) use ($term) {
                    $where->where('nickname', 'like', "%{$term}%")
                        ->orWhereHas('profile', fn ($profile) => $profile->where(
                            'full_name',
                            'like',
                            "%{$term}%"
                        ));
                });
            })
            ->whereNotIn('id', $hiddenDriverIds)
            ->orderByDesc('rating')
            ->paginate(12)
            ->withQueryString();

        return view('drivers.index', compact('drivers'));
    }

    public function show(Driver $driver)
    {
        $this->authorize('view', $driver);

        $viewer = auth()->user()?->driver;
        $isOwnProfile = $viewer !== null && $viewer->id === $driver->id;

        $driver->load([
            'profile',
            'entries.race.seasons',
            'groups',
            'teams.group',
        ]);

        $entries = $driver->entries->values();
        $started = $entries->filter(fn ($entry) => $entry->finish_position !== null);

        $bestQualifying = $entries->pluck('qualifying_time_ms')->filter()->min();
        $avgQualifying = $entries->pluck('qualifying_time_ms')->filter()->avg();

        $penaltyQuery = $driver->penalties()
            ->where('status', '!=', RacePenaltyStatus::Cancelled->value);

        $penaltyCalls = (clone $penaltyQuery)->count();
        $penaltySeconds = (int) (clone $penaltyQuery)->sum('seconds');

        $stats = [
            'starts' => $started->count(),
            'wins' => $started->filter(fn ($entry) => $entry->finish_position === 1)->count(),
            'podiums' => $started->filter(fn ($entry) => in_array($entry->finish_position, [1, 2, 3], true))->count(),
            'poles' => $entries->filter(fn ($entry) => $entry->grid_position === 1)->count(),
            'dnfs' => $entries->filter(fn ($entry) => $entry->status === RaceDriverStatus::Dnf)->count(),
            'clean' => $started->filter(fn ($entry) => ($entry->penalty_total_seconds ?? 0) === 0)->count(),
            'penaltyCalls' => $penaltyCalls,
            'penaltySeconds' => $penaltySeconds,
            'bestQualifyingMs' => $bestQualifying,
            'avgQualifyingMs' => $avgQualifying,
        ];

        $recentEntries = $entries
            ->filter(fn ($entry) => $entry->race !== null)
            ->sortByDesc(fn ($entry) => $entry->race->date)
            ->take(8)
            ->values();

        $recentForm = $entries
            ->filter(fn ($entry) => $entry->race !== null)
            ->sortByDesc(fn ($entry) => $entry->race->date)
            ->take(6)
            ->map(fn ($entry) => [
                'finish' => $entry->finish_position,
                'status' => $entry->status?->value,
            ])
            ->reverse()
            ->values();

        $viewerGroupIds = $viewer ? $viewer->groups()->pluck('groups.id')->all() : [];

        $visibleGroups = $driver->groups->values();
        $visibleTeams = $driver->teams->values();

        if (! $isOwnProfile) {
            $visibleGroups = $visibleGroups->filter(
                fn ($group) => in_array($group->id, $viewerGroupIds, true)
            )->values();

            $visibleTeams = $visibleTeams->filter(
                fn ($team) => $visibleGroups->contains('id', $team->group_id)
            )->values();
        }

        $availabilityByGroup = $visibleGroups->mapWithKeys(
            fn ($group) => [$group->id => $group->pivot->availability]
        );

        $championships = collect();
        foreach ($entries as $entry) {
            foreach ($entry->race?->seasons ?? [] as $season) {
                $championships->push([
                    'seasonId' => $season->id,
                    'raceId' => $entry->race_id,
                    'name' => $season->name,
                    'statusLabel' => ucfirst($season->status->value),
                ]);
            }
        }

        $championships = $championships
            ->groupBy('seasonId')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'id' => $first['seasonId'],
                    'name' => $first['name'],
                    'statusLabel' => $first['statusLabel'],
                    'rounds' => $rows->pluck('raceId')->unique()->count(),
                ];
            })
            ->values();

        return view('drivers.profile', compact(
            'driver',
            'stats',
            'recentEntries',
            'recentForm',
            'visibleGroups',
            'visibleTeams',
            'availabilityByGroup',
            'championships',
            'isOwnProfile'
        ));
    }

    public function edit(Driver $driver)
    {
        $this->authorize('update', $driver);

        return view('drivers.edit', compact('driver'));
    }

    public function update(Driver $driver, UpdateDriverProfileRequest $request)
    {
        $this->authorize('update', $driver);

        $driver->profile()->update([
            'full_name' => $request->full_name,
        ]);

        $driver->update([
            'nickname' => $request->filled('nickname')
                ? strtoupper($request->nickname)
                : null,
            'racing_number' => $request->racing_number,
            'avatar_color' => $request->avatar_color ?? $driver->avatar_color,
            'avatar_text_color' => $request->avatar_text_color ?? $driver->avatar_text_color,
        ]);

        return redirect()
            ->route('drivers.show', $driver)
            ->with('status', 'Driver profile updated.');
    }
}
