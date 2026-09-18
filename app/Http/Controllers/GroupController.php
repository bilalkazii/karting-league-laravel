<?php

namespace App\Http\Controllers;

use App\Enums\DriverAvailability;
use App\Enums\GroupPrivacy;
use App\Enums\GroupRole;
use App\Enums\RaceStatus;
use App\Enums\SeasonStatus;
use App\Http\Requests\CreateGroupRequest;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $driver = auth()->user()?->driver;

        if (! $driver) {
            return view('groups.index', [
                'groups' => collect(),
                'memberCounts' => collect(),
            ]);
        }

        $groups = $driver->groups()->orderBy('name')->get();
        $memberCounts = $groups->mapWithKeys(
            fn (Group $group) => [$group->id => $group->members()->count()]
        );

        return view('groups.index', compact('groups', 'memberCounts'));
    }

    public function create()
    {
        return view('groups.create');
    }

    public function store(CreateGroupRequest $request)
    {
        $driver = $request->user()->driver;

        $initials = strtoupper(
            collect(preg_split('/\s+/', trim($request->name)))
                ->filter()
                ->map(fn ($word) => mb_substr($word, 0, 1))
                ->take(2)
                ->implode('')
        ) ?: 'NA';

        $group = Group::create([
            'name' => $request->name,
            'description' => $request->description ?? '',
            'logo_initials' => $initials,
            'logo_color' => '#e11d48',
            'logo_text_color' => '#ffffff',
            'cover_color' => '#27272a',
            'privacy' => GroupPrivacy::Private,
            'created_by' => $driver->id,
        ]);

        $group->members()->attach($driver->id, [
            'role' => GroupRole::Admin->value,
            'availability' => DriverAvailability::Available->value,
            'joined_at' => now(),
        ]);

        return redirect()->route('groups.show', $group);
    }

    public function show(Group $group)
    {
        $this->authorize('view', $group);

        $members = $group->members()->with('profile')->get();
        $memberCount = $members->count();

        $admins = $members
            ->filter(fn ($member) => $member->pivot->role === GroupRole::Admin->value)
            ->values();

        $upcomingRace = Race::where('group_id', $group->id)
            ->whereIn('status', [
                RaceStatus::Draft->value,
                RaceStatus::Lobby->value,
                RaceStatus::Qualifying->value,
                RaceStatus::Grid->value,
            ])
            ->where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        $recentCompletedRaces = Race::where('group_id', $group->id)
            ->where('status', RaceStatus::Completed->value)
            ->orderByDesc('date')
            ->limit(3)
            ->get();

        $recentResults = $recentCompletedRaces->map(function (Race $race) {
            $winner = $race->drivers()->wherePivot('finish_position', 1)->first();

            return [
                'name' => $race->name,
                'date' => $race->date?->format('d M Y'),
                'venue_name' => $race->venue_name,
                'winner' => $winner,
            ];
        });

        $currentChampionship = Season::where('group_id', $group->id)
            ->where('status', SeasonStatus::Active->value)
            ->withCount('races')
            ->first();

        $championshipRound = $currentChampionship
            ? $currentChampionship->races()->where('status', RaceStatus::Completed->value)->count() + 1
            : 1;

        $teams = Team::where('group_id', $group->id)
            ->with('members.profile')
            ->orderBy('name')
            ->get();

        $currentUserPivot = $members->firstWhere('id', auth()->user()?->driver?->id)?->pivot;
        $currentUserAvailability = $currentUserPivot?->availability
            ?? DriverAvailability::Available->value;

        return view('groups.show', compact(
            'group',
            'members',
            'memberCount',
            'admins',
            'upcomingRace',
            'recentResults',
            'currentChampionship',
            'championshipRound',
            'teams',
            'currentUserAvailability'
        ));
    }

    public function members(Group $group)
    {
        $this->authorize('view', $group);

        $members = $group->members()
            ->with('profile')
            ->orderBy('profile_id')
            ->get();
        $memberCount = $members->count();

        $currentUserMember = $members->firstWhere('id', auth()->user()?->driver?->id);
        $isOrganizer = $currentUserMember
            && in_array($currentUserMember->pivot->role, [
                GroupRole::Admin->value,
                GroupRole::Organizer->value,
            ], true);
        $canManageRoles = $currentUserMember?->pivot?->role === GroupRole::Admin->value;

        $availableDrivers = Driver::whereNotIn('id', $members->pluck('id'))
            ->orderBy('nickname')
            ->get();

        return view('groups.members', compact(
            'group',
            'members',
            'memberCount',
            'isOrganizer',
            'canManageRoles',
            'availableDrivers'
        ));
    }

    public function addMember(Request $request, Group $group)
    {
        $this->authorize('manageMembers', $group);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', Rule::exists('drivers', 'id')],
        ]);

        abort_if(
            $group->members()->where('drivers.id', $validated['driver_id'])->exists(),
            422,
            'That driver is already a member.'
        );

        $group->members()->attach($validated['driver_id'], [
            'role' => GroupRole::Member->value,
            'availability' => DriverAvailability::Available->value,
            'joined_at' => now(),
        ]);

        return redirect()->route('groups.members', $group);
    }

    public function updateMemberRole(Request $request, Group $group, Driver $driver)
    {
        $this->authorize('manageRoles', $group);

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_column(GroupRole::cases(), 'value'))],
        ]);

        $target = $group->members()->where('drivers.id', $driver->id)->first();
        abort_unless($target !== null, 404);

        $newRole = $validated['role'];
        $currentRole = $target->pivot->role;
        $adminCount = $group->members()->wherePivot('role', GroupRole::Admin->value)->count();

        if ($currentRole === GroupRole::Admin->value && $newRole !== GroupRole::Admin->value && $adminCount <= 1) {
            abort(422, 'A group must always keep at least one admin.');
        }

        $group->members()->updateExistingPivot($driver->id, ['role' => $newRole]);

        return redirect()->route('groups.members', $group);
    }

    public function removeMember(Group $group, Driver $driver)
    {
        $this->authorize('manageMembers', $group);

        $target = $group->members()->where('drivers.id', $driver->id)->first();
        abort_unless($target !== null, 404);

        if ($target->pivot->role === GroupRole::Admin->value
            && $group->members()->wherePivot('role', GroupRole::Admin->value)->count() <= 1) {
            abort(422, 'A group must always keep at least one admin.');
        }

        $group->members()->detach($driver->id);

        return redirect()->route('groups.members', $group);
    }

    public function updateAvailability(Group $group, Request $request)
    {
        $this->authorize('view', $group);

        $validated = $request->validate([
            'availability' => ['required', Rule::in(array_column(DriverAvailability::cases(), 'value'))],
        ]);

        $driver = $request->user()?->driver;

        if (! $driver) {
            abort(403);
        }

        $driver->groups()->updateExistingPivot($group->id, [
            'availability' => $validated['availability'],
        ]);

        return redirect()->back();
    }
}
