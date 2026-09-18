<?php

namespace App\Http\Controllers;

use App\Enums\GroupRole;
use App\Enums\RaceStatus;
use App\Http\Requests\StoreRaceRequest;
use App\Http\Requests\UpdateRaceRequest;
use App\Models\Race;
use App\Services\RaceService;
use App\Support\RaceUtils;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RaceController extends Controller
{
    use AuthorizesRequests;

    private const TAB_ENABLED_STATUSES = [
        'overview' => ['draft', 'lobby', 'qualifying', 'grid', 'racing', 'completed', 'cancelled'],
        'setup' => ['draft', 'lobby'],
        'lobby' => ['draft', 'lobby', 'qualifying'],
        'qualifying' => ['lobby', 'qualifying', 'grid', 'racing', 'completed'],
        'grid' => ['qualifying', 'grid', 'racing', 'completed'],
        'control' => ['grid', 'racing'],
        'results' => ['completed'],
    ];

    private const TABS = ['overview', 'setup', 'lobby', 'qualifying', 'grid', 'control', 'results'];

    public function __construct(private readonly RaceService $races) {}

    public function index(): View
    {
        $driver = auth()->user()?->driver;

        $races = collect();
        if ($driver) {
            $groupIds = $driver->groups()->pluck('groups.id');

            $query = Race::query()
                ->with(['group', 'organizer.profile'])
                ->withCount('entries')
                ->whereIn('group_id', $groupIds);

            if ($q = trim((string) request('q'))) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('venue_name', 'like', "%{$q}%");
                });
            }

            if ($status = request('status')) {
                $allowed = array_column(RaceStatus::cases(), 'value');
                if (in_array($status, $allowed, true)) {
                    $query->where('status', $status);
                }
            }

            $races = $query->orderByDesc('date')->orderByDesc('start_time')->get();
        }

        $statuses = RaceStatus::cases();

        return view('races.index', [
            'races' => $races,
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

        return view('races.create', compact('groups'));
    }

    public function store(StoreRaceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $race = Race::create([
            'group_id' => $validated['group_id'],
            'name' => $validated['name'],
            'venue_name' => $validated['venue_name'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'format' => $validated['format'],
            'status' => RaceStatus::Draft->value,
            'organizer_id' => $request->user()->driver?->id,
            'qualifying_lap_count' => $validated['qualifying_lap_count'],
            'rules' => $validated['rules'] ?? '',
        ]);

        return redirect()->route('races.show', $race);
    }

    public function show(Race $race): View|RedirectResponse
    {
        $this->authorize('view', $race);

        $tab = request('tab', 'overview');
        if (! in_array($tab, self::TABS, true)) {
            return redirect()->route('races.show', $race);
        }

        $status = $race->status->value;
        if (! in_array($status, self::TAB_ENABLED_STATUSES[$tab], true)) {
            $tab = 'overview';
        }

        $race->load([
            'group',
            'organizer.profile',
            'entries.driver.profile',
            'penalties.driver',
            'events.driver',
        ]);

        $entries = RaceUtils::entriesToArrays($race->entries);
        $currentDriver = auth()->user()?->driver;
        $season = $race->seasons()->with(['scoring', 'scoringPoints'])->first();

        return view('races.show', [
            'race' => $race,
            'entries' => $entries,
            'currentEntry' => $currentDriver ? $race->entries->firstWhere('driver_id', $currentDriver->id) : null,
            'currentDriver' => $currentDriver,
            'currentTab' => $tab,
            'tabs' => self::TABS,
            'enabledStatuses' => self::TAB_ENABLED_STATUSES,
            'gridRows' => RaceUtils::computeGridRows($entries),
            'qualifyingList' => RaceUtils::qualifyingRows($entries),
            'poleMs' => RaceUtils::poleTime($entries),
            'results' => RaceUtils::buildFinalResults($entries),
            'activePenalties' => $race->penalties->reject(fn ($p) => $p->status->value === 'cancelled')->values(),
            'events' => $race->events->sortByDesc('occurred_at')->take(20),
            'season' => $season,
            'charImg' => mb_strtoupper(mb_substr($race->name, 0, 1)),
        ]);
    }

    public function edit(Race $race): View
    {
        $this->authorize('update', $race);

        return view('races.edit', compact('race'));
    }

    public function update(UpdateRaceRequest $request, Race $race): RedirectResponse
    {
        $this->authorize('update', $race);

        $race->update([
            'name' => $request->input('name'),
            'venue_name' => $request->input('venue_name'),
            'date' => $request->input('date'),
            'start_time' => $request->input('start_time'),
            'format' => $request->input('format'),
            'qualifying_lap_count' => $request->input('qualifying_lap_count'),
            'rules' => $request->input('rules', ''),
        ]);

        return redirect()->route('races.show', $race);
    }

    public function destroy(Race $race): RedirectResponse
    {
        $this->authorize('delete', $race);

        $race->delete();

        return redirect()->route('races');
    }
}
