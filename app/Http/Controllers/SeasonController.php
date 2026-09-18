<?php

namespace App\Http\Controllers;

use App\Enums\GroupRole;
use App\Enums\RaceStatus;
use App\Enums\ScoringMode;
use App\Enums\SeasonStatus;
use App\Http\Requests\StoreSeasonRequest;
use App\Http\Requests\UpdateSeasonRequest;
use App\Models\Group;
use App\Models\ScoringPoint;
use App\Models\Season;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeasonController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $driver = auth()->user()?->driver;

        $seasons = collect();
        $managedSeasonIds = collect();

        if ($driver) {
            $groupIds = $driver->groups()->pluck('groups.id');
            $managedGroupIds = $driver->groups()
                ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Organizer->value])
                ->pluck('groups.id')
                ->all();

            $query = Season::query()
                ->with('group')
                ->withCount('races')
                ->whereIn('group_id', $groupIds);

            if ($q = trim((string) request('q'))) {
                $query->where('name', 'like', "%{$q}%");
            }

            if ($status = request('status')) {
                $allowed = array_column(SeasonStatus::cases(), 'value');
                if (in_array($status, $allowed, true)) {
                    $query->where('status', $status);
                }
            }

            $seasons = $query->orderByDesc('start_date')->orderByDesc('id')->get();
            $managedSeasonIds = $seasons->whereIn('group_id', $managedGroupIds)->pluck('id');
        }

        $statuses = SeasonStatus::cases();

        return view('championship.index', [
            'seasons' => $seasons,
            'managedSeasonIds' => $managedSeasonIds,
            'statuses' => $statuses,
        ]);
    }

    public function create(): View
    {
        $driver = auth()->user()?->driver;

        $groups = $driver
            ? $driver->groups()
                ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Organizer->value])
                ->orderBy('name')
                ->get()
            : collect();

        return view('seasons.create', compact('groups'));
    }

    public function store(StoreSeasonRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $group = Group::findOrFail($validated['group_id']);
        $this->authorize('create', [Season::class, $group]);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => $validated['name'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        $this->attachDefaultScoring($season);

        return redirect()->route('championship.show', $season);
    }

    public function show(Season $season): View
    {
        $this->authorize('view', $season);

        $season->load([
            'group',
            'scoring',
            'scoringPoints',
            'records.driver',
            'awards.driver',
            'awards.team',
            'races' => fn ($query) => $query->orderByPivot('round_number'),
            'races.drivers.profile',
        ]);

        $rounds = $season->races->map(function ($race) {
            return [
                'race' => $race,
                'round' => (int) $race->pivot->round_number,
                'entriesCount' => $race->drivers->count(),
                'winner' => $race->drivers->firstWhere('pivot.finish_position', 1),
            ];
        });

        $participatingDrivers = $season->races->flatMap(
            fn ($race) => $race->drivers
        )->keyBy('id')->values()->sortBy(
            static fn ($driver) => $driver->profile?->full_name ?? $driver->nickname
        )->values();

        $completedRaces = $season->races->where('status', RaceStatus::Completed->value)->count();

        $upcomingRace = $season->races
            ->whereIn('status', [
                RaceStatus::Draft->value,
                RaceStatus::Lobby->value,
                RaceStatus::Qualifying->value,
                RaceStatus::Grid->value,
            ])
            ->where(fn ($race) => $race->date?->gte(today()))
            ->sortBy(static fn ($race) => $race->date?->format('Y-m-d').' '.$race->start_time)
            ->first();

        $canManage = auth()->user()->can('update', $season);

        return view('championship.show', compact(
            'season',
            'rounds',
            'participatingDrivers',
            'completedRaces',
            'upcomingRace',
            'canManage'
        ));
    }

    public function edit(Season $season): View
    {
        $this->authorize('update', $season);

        return view('seasons.edit', [
            'season' => $season,
            'statuses' => SeasonStatus::cases(),
        ]);
    }

    public function update(UpdateSeasonRequest $request, Season $season): RedirectResponse
    {
        $this->authorize('update', $season);

        $validated = $request->validated();

        $season->update([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return redirect()->route('championship.show', $season);
    }

    public function destroy(Season $season): RedirectResponse
    {
        $this->authorize('delete', $season);

        $season->delete();

        return redirect()->route('championship');
    }

    private function attachDefaultScoring(Season $season): void
    {
        $season->scoring()->create([
            'mode' => ScoringMode::Automatic,
            'pole_position_points' => 1,
            'fastest_lap_points' => 0,
            'participation_points' => 0,
            'dnf_points' => 0,
            'dns_points' => 0,
            'penalty_adjustment_enabled' => false,
        ]);

        $pointsByPosition = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4];

        foreach ($pointsByPosition as $position => $points) {
            ScoringPoint::create([
                'season_id' => $season->id,
                'position' => $position,
                'points' => $points,
            ]);
        }
    }
}
