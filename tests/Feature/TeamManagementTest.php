<?php

namespace Tests\Feature;

use App\Enums\GroupRole;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithDriver(?Group $group = null, string $role = GroupRole::Admin->value): User
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->for(Profile::factory()->create(['user_id' => $user->id]))->create();

        if ($group) {
            $group->members()->attach($driver->id, [
                'role' => $role,
                'availability' => 'available',
                'joined_at' => now(),
            ]);
        }

        return $user->refresh();
    }

    private function groupWithDriver(User $user, string $role = GroupRole::Admin->value): Group
    {
        $group = Group::factory()->create();
        $group->members()->attach($user->driver->id, [
            'role' => $role,
            'availability' => 'available',
            'joined_at' => now(),
        ]);

        return $group;
    }

    private function memberDriver(Group $group, string $name): Driver
    {
        $driver = Driver::factory()->for(Profile::factory()->create(['full_name' => $name]))->create();
        $group->members()->attach($driver->id, [
            'role' => GroupRole::Member->value,
            'availability' => 'available',
            'joined_at' => now(),
        ]);

        return $driver;
    }

    public function test_guests_cannot_view_teams(): void
    {
        $group = Group::factory()->create();

        $this->get(route('groups.teams', $group))->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_view_teams(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver();

        $this->actingAs($user)
            ->get(route('groups.teams', $group))
            ->assertForbidden();
    }

    public function test_plain_member_cannot_create_team(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user, GroupRole::Member->value);

        $this->actingAs($user)
            ->post(route('groups.teams.store', $group), [
                'group_id' => $group->id,
                'name' => 'Rogue Squad',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('teams', ['name' => 'Rogue Squad']);
    }

    public function test_organizer_can_create_team_with_pairing(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user, GroupRole::Organizer->value);
        $first = $this->memberDriver($group, 'Zahed Khan');
        $second = $this->memberDriver($group, 'Khalid');

        $this->actingAs($user)
            ->post(route('groups.teams.store', $group), [
                'group_id' => $group->id,
                'name' => 'Team Alpha',
                'driver_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('groups.teams', $group));

        $team = Team::where('name', 'Team Alpha')->firstOrFail();

        $this->assertSame(
            [$first->id, $second->id],
            $team->members()->orderBy('drivers.id')->pluck('drivers.id')->map('intval')->all(),
        );
    }

    public function test_team_name_must_be_unique_within_group(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        Team::factory()->create(['group_id' => $group->id, 'name' => 'Apex Racing']);

        $this->actingAs($user)
            ->post(route('groups.teams.store', $group), [
                'group_id' => $group->id,
                'name' => 'Apex Racing',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Team::where('name', 'Apex Racing')->count());
    }

    public function test_organizer_cannot_create_a_team_in_a_group_they_do_not_manage(): void
    {
        $user = $this->userWithDriver();
        $this->groupWithDriver($user);
        $otherGroup = Group::factory()->create();

        $this->actingAs($user)
            ->post(route('groups.teams.store', $otherGroup), [
                'group_id' => $otherGroup->id,
                'name' => 'Apex Racing',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('teams', ['name' => 'Apex Racing']);
    }

    public function test_team_cannot_exceed_two_drivers(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');
        $b = $this->memberDriver($group, 'Driver B');
        $c = $this->memberDriver($group, 'Driver C');

        $this->actingAs($user)
            ->post(route('groups.teams.store', $group), [
                'group_id' => $group->id,
                'name' => 'Too Big',
                'driver_ids' => [$a->id, $b->id, $c->id],
            ])
            ->assertSessionHasErrors('driver_ids');

        $this->assertDatabaseMissing('teams', ['name' => 'Too Big']);
    }

    public function test_team_rejects_duplicate_driver_selection(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');

        $this->actingAs($user)
            ->post(route('groups.teams.store', $group), [
                'group_id' => $group->id,
                'name' => 'Double Up',
                'driver_ids' => [$a->id, $a->id],
            ])
            ->assertSessionHasErrors('driver_ids');
    }

    public function test_team_rejects_drivers_outside_the_group(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $outsider = Driver::factory()->for(Profile::factory()->create())->create();

        $this->actingAs($user)
            ->post(route('groups.teams.store', $group), [
                'group_id' => $group->id,
                'name' => 'Outsiders',
                'driver_ids' => [$outsider->id],
            ])
            ->assertSessionHasErrors('driver_ids');

        $this->assertDatabaseMissing('teams', ['name' => 'Outsiders']);
    }

    public function test_renaming_a_team_leaves_drivers_and_results_untouched(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Zahed Khan');
        $b = $this->memberDriver($group, 'Khalid');

        $race = Race::factory()->create(['group_id' => $group->id, 'status' => 'completed']);
        RaceEntry::create([
            'race_id' => $race->id,
            'driver_id' => $a->id,
            'kart_number' => 7,
            'status' => 'finished',
            'confirmed' => true,
            'ready' => true,
            'grid_position' => 1,
            'grid_penalty_seconds' => 0,
            'qualifying_time_ms' => 41750,
            'qualifying_status' => 'completed',
            'finish_position' => 1,
            'penalty_total_seconds' => 0,
            'notes' => '',
        ]);

        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Old Name']);
        $team->members()->attach([$a->id, $b->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->patch(route('teams.update', $team), ['name' => 'New Name'])
            ->assertRedirect(route('groups.teams', $group))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'New Name']);
        // Driver identity and history untouched.
        $this->assertDatabaseHas('drivers', ['id' => $a->id, 'nickname' => $a->nickname]);
        $this->assertDatabaseHas('profiles', ['id' => $a->profile_id, 'full_name' => 'Zahed Khan']);
        $this->assertDatabaseHas('race_entries', [
            'race_id' => $race->id,
            'driver_id' => $a->id,
            'finish_position' => 1,
        ]);
        $this->assertDatabaseCount('team_members', 2);
    }

    public function test_plain_member_cannot_rename_a_team(): void
    {
        $admin = $this->userWithDriver();
        $group = $this->groupWithDriver($admin);
        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Untouched']);

        $member = $this->userWithDriver($group, GroupRole::Member->value);

        $this->actingAs($member)
            ->patch(route('teams.update', $team), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Untouched']);
    }

    public function test_rename_validates_against_duplicate_name_in_same_group(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $first = Team::factory()->create(['group_id' => $group->id, 'name' => 'Alpha']);
        $second = Team::factory()->create(['group_id' => $group->id, 'name' => 'Beta']);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->patch(route('teams.update', $second), ['name' => 'Alpha'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('teams', ['id' => $second->id, 'name' => 'Beta']);
        $this->assertDatabaseHas('teams', ['id' => $first->id, 'name' => 'Alpha']);
    }

    public function test_keeping_its_own_name_on_rename_is_allowed(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Steady']);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->patch(route('teams.update', $team), ['name' => 'Steady'])
            ->assertSessionHasNoErrors();
    }

    public function test_membership_can_be_replaced_without_touching_history(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');
        $b = $this->memberDriver($group, 'Driver B');
        $c = $this->memberDriver($group, 'Driver C');

        $race = Race::factory()->create(['group_id' => $group->id, 'status' => 'completed']);
        foreach ([[$a, 1], [$b, 2]] as [$driver, $position]) {
            RaceEntry::create([
                'race_id' => $race->id,
                'driver_id' => $driver->id,
                'kart_number' => $position,
                'status' => 'finished',
                'confirmed' => true,
                'ready' => true,
                'grid_position' => $position,
                'grid_penalty_seconds' => 0,
                'qualifying_time_ms' => 40000 + $position,
                'qualifying_status' => 'completed',
                'finish_position' => $position,
                'penalty_total_seconds' => 0,
                'notes' => '',
            ]);
        }

        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Swappable']);
        $team->members()->attach([$a->id, $b->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), [
                'driver_ids' => [$a->id, $c->id],
                'confirm_removals' => 1,
            ])
            ->assertRedirect(route('groups.teams', $group));

        $this->assertSame(
            [$a->id, $c->id],
            $team->fresh()->members()->orderBy('drivers.id')->pluck('drivers.id')->map('intval')->all(),
        );
        // Historical results are untouched by the membership change.
        $this->assertDatabaseHas('race_entries', ['race_id' => $race->id, 'driver_id' => $a->id, 'finish_position' => 1]);
        $this->assertDatabaseHas('race_entries', ['race_id' => $race->id, 'driver_id' => $b->id, 'finish_position' => 2]);
    }

    /**
     * Un-ticking a driver posts a shorter lineup, so removal has to be an
     * explicit act rather than a side effect of saving.
     */
    public function test_removing_a_member_requires_explicit_confirmation(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');
        $b = $this->memberDriver($group, 'Driver B');

        $team = Team::factory()->create(['group_id' => $group->id]);
        $team->members()->attach([$a->id, $b->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), ['driver_ids' => [$a->id]])
            ->assertSessionHasErrors('driver_ids');

        // Nothing was removed by the silent attempt.
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $team->fresh()->members()->pluck('drivers.id')->map('intval')->all(),
        );
    }

    public function test_the_removal_error_names_the_driver(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');
        $b = $this->memberDriver($group, 'Khalid Rahman');

        $team = Team::factory()->create(['group_id' => $group->id]);
        $team->members()->attach([$a->id, $b->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), ['driver_ids' => [$a->id]])
            ->assertSessionHasErrors('driver_ids');

        $message = session('errors')->first('driver_ids');
        $this->assertStringContainsString('Khalid Rahman', $message);
    }

    public function test_adding_a_driver_needs_no_removal_confirmation(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');
        $b = $this->memberDriver($group, 'Driver B');

        $team = Team::factory()->create(['group_id' => $group->id]);
        $team->members()->attach([$a->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), ['driver_ids' => [$a->id, $b->id]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $team->fresh()->members()->pluck('drivers.id')->map('intval')->all(),
        );
    }

    public function test_membership_rejects_more_than_two_drivers(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');
        $b = $this->memberDriver($group, 'Driver B');
        $c = $this->memberDriver($group, 'Driver C');

        $team = Team::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), ['driver_ids' => [$a->id, $b->id, $c->id]])
            ->assertSessionHasErrors('driver_ids');

        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_membership_rejects_driver_outside_group(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $outsider = Driver::factory()->for(Profile::factory()->create())->create();
        $team = Team::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), ['driver_ids' => [$outsider->id]])
            ->assertSessionHasErrors('driver_ids');

        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_deleting_a_team_keeps_drivers_and_race_results(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Driver A');

        $race = Race::factory()->create(['group_id' => $group->id, 'status' => 'completed']);
        RaceEntry::create([
            'race_id' => $race->id,
            'driver_id' => $a->id,
            'kart_number' => 7,
            'status' => 'finished',
            'confirmed' => true,
            'ready' => true,
            'grid_position' => 1,
            'grid_penalty_seconds' => 0,
            'qualifying_time_ms' => 41750,
            'qualifying_status' => 'completed',
            'finish_position' => 1,
            'penalty_total_seconds' => 0,
            'notes' => '',
        ]);

        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Temporary']);
        $team->members()->attach($a->id, ['joined_at' => now()]);

        $this->actingAs($user)
            ->delete(route('teams.destroy', $team))
            ->assertRedirect(route('groups.teams', $group));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('drivers', ['id' => $a->id]);
        $this->assertDatabaseHas('race_entries', ['race_id' => $race->id, 'driver_id' => $a->id, 'finish_position' => 1]);
    }

    public function test_existing_larger_team_can_still_be_edited_without_losing_a_driver(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Bilal Darji');
        $b = $this->memberDriver($group, 'Ahmad Rehman');
        $c = $this->memberDriver($group, 'Arjun Mehta');

        // A pre-existing three-driver team (like the seeded Apex Racing) must
        // stay editable; the pairing cap applies to new/larger lineups only.
        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Apex Racing']);
        $team->members()->attach([$a->id, $b->id, $c->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->patch(route('teams.update', $team), ['name' => 'Apex Racing Elite'])
            ->assertRedirect(route('groups.teams', $group))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->from(route('groups.teams', $group))
            ->put(route('teams.members.update', $team), ['driver_ids' => [$a->id, $b->id, $c->id]])
            ->assertRedirect(route('groups.teams', $group))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Apex Racing Elite']);
        $this->assertSame(3, $team->fresh()->members()->count());
    }

    public function test_teams_page_lists_group_teams_and_membership(): void
    {
        $user = $this->userWithDriver();
        $group = $this->groupWithDriver($user);
        $a = $this->memberDriver($group, 'Zahed Khan');
        $b = $this->memberDriver($group, 'Khalid');

        $team = Team::factory()->create(['group_id' => $group->id, 'name' => 'Editable Squad']);
        $team->members()->attach([$a->id, $b->id], ['joined_at' => now()]);

        $this->actingAs($user)
            ->get(route('groups.teams', $group))
            ->assertOk()
            ->assertSee('Editable Squad')
            ->assertSee('Zahed Khan')
            ->assertSee('Khalid')
            ->assertSee('Save name');
    }
}
