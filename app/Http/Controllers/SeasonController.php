<?php

namespace App\Http\Controllers;

use App\Enums\GroupRole;
use App\Enums\RaceStatus;
use App\Enums\ScoringMode;
use App\Enums\SeasonStatus;
use App\Http\Requests\AddRaceToSeasonRequest;
use App\Http\Requests\StoreSeasonRequest;
use App\Http\Requests\UpdateSeasonRequest;
use App\Models\Group;
use App\Models\Race;
use App\Models\ScoringPoint;
use App\Models\Season;
use App\Models\Team;
use App\Support\StandingsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'races.entries',
            'races.penalties',
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
        $canDelete = $season->races->isEmpty()
            && $season->records->isEmpty()
            && $season->awards->isEmpty();

        $scoring = StandingsService::scoringFor($season);

        $standings = StandingsService::computeStandings($season->races, $scoring)['final'];
        $standingsDrivers = $participatingDrivers->keyBy('id');

        // One leaderboard column per scored event, so each row's total can be
        // reconciled against the underlying results.
        $events = StandingsService::eventBreakdown($season->races, $scoring);
        $progress = StandingsService::progress($season->races);

        // Constructors' standings: sum of each team's members' season points.
        $seasonTeams = Team::where('group_id', $season->group_id)
            ->with('members.profile')
            ->orderBy('name')
            ->get();
        $teamStandings = StandingsService::teamStandings($seasonTeams, $standings);

        $eligibleRaces = $canManage
            ? Race::query()
                ->where('group_id', $season->group_id)
                ->whereNotIn('id', $season->races->pluck('id'))
                ->where('status', '!=', RaceStatus::Cancelled->value)
                ->orderByDesc('date')
                ->orderByDesc('start_time')
                ->get()
            : collect();

        return view('championship.show', compact(
            'season',
            'rounds',
            'participatingDrivers',
            'standings',
            'standingsDrivers',
            'completedRaces',
            'upcomingRace',
            'canManage',
            'canDelete',
            'eligibleRaces',
            'events',
            'progress',
            'teamStandings'
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

        $hasHistory = $season->races()->exists()
            || $season->records()->exists()
            || $season->awards()->exists();

        abort_if(
            $hasHistory,
            422,
            'This season has races, records, or awards attached; deleting it would erase championship history.'
        );

        $season->delete();

        return redirect()->route('championship');
    }

    public function addRace(AddRaceToSeasonRequest $request, Season $season): RedirectResponse
    {
        $race = Race::findOrFail($request->integer('race_id'));

        $this->authorize('manage', $race);

        try {
            DB::transaction(function () use ($season, $race): void {
                // Lock the season row so concurrent requests cannot compute the
                // same round number; the unique (season_id, round_number) index
                // is the final backstop.
                Season::query()->whereKey($season->getKey())->lockForUpdate()->first();

                if ($season->races()->where('races.id', $race->id)->exists()) {
                    abort(422, 'That race is already part of this season.');
                }

                $used = DB::table('season_races')
                    ->where('season_id', $season->getKey())
                    ->pluck('round_number')
                    ->map(static fn ($number): int => (int) $number)
                    ->all();

                // Rule: assign the lowest positive round number not already used.
                $round = 1;
                while (in_array($round, $used, true)) {
                    $round++;
                }

                $season->races()->attach($race->id, ['round_number' => $round]);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'race_id' => 'That round was just assigned by someone else. Please try again.',
            ]);
        }

        return redirect()
            ->route('championship.show', $season)
            ->with('status', $race->name.' added to '.$season->name.'.');
    }

    private function attachDefaultScoring(Season $season): void
    {
        // Points are awarded by finishing position only: a new season gets no
        // pole-position bonus and no fastest-lap bonus.
        $season->scoring()->create([
            'mode' => ScoringMode::Automatic,
            'pole_position_points' => 0,
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
