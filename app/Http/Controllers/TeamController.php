<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamMembersRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function index(Group $group): View
    {
        $this->authorize('view', $group);

        $teams = Team::where('group_id', $group->id)
            ->with('members.profile')
            ->orderBy('name')
            ->get();

        $members = $group->members()->with('profile')->orderBy('profile_id')->get();
        $canManage = auth()->user()->can('createInGroup', [Team::class, $group]);

        return view('teams.index', compact('group', 'teams', 'members', 'canManage'));
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $group = Group::findOrFail($request->integer('group_id'));
        $this->authorize('createInGroup', [Team::class, $group]);

        $validated = $request->validated();

        $team = Team::create([
            'group_id' => $group->id,
            'name' => $validated['name'],
            'logo_initials' => self::initialsFor($validated['name']),
            'logo_color' => '#c43c2d',
            'logo_text_color' => '#ffffff',
        ]);

        $this->syncMembers($team, $validated['driver_ids'] ?? []);

        return redirect()
            ->route('groups.teams', $group)
            ->with('status', $team->name.' created.');
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $team->update(['name' => $request->validated('name')]);

        return redirect()
            ->route('groups.teams', $team->group)
            ->with('status', 'Team renamed.');
    }

    public function updateMembers(UpdateTeamMembersRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $this->syncMembers($team, $request->validated('driver_ids', []));

        return redirect()
            ->route('groups.teams', $team->group)
            ->with('status', 'Team lineup updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $group = $team->group;
        $name = $team->name;

        // Deleting a team removes only team membership; drivers and every race
        // result they recorded stay intact.
        $team->delete();

        return redirect()
            ->route('groups.teams', $group)
            ->with('status', $name.' removed.');
    }

    /**
     * @param  array<int, mixed>  $driverIds
     */
    private function syncMembers(Team $team, array $driverIds): void
    {
        $ids = collect($driverIds)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $team->members()->sync(
            $ids->mapWithKeys(static fn (int $id): array => [$id => ['joined_at' => now()]])->all()
        );
    }

    private static function initialsFor(string $name): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->map(static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return $initials !== '' ? $initials : 'NA';
    }
}
