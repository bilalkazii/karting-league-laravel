<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Race;
use App\Models\RaceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RacesTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    private function kartingCrewGroup(): Group
    {
        return Group::where('name', 'Karting Crew')->firstOrFail();
    }

    private function seededRace(string $name): Race
    {
        return Race::where('name', $name)->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Driver}
     */
    private function userWithDriver(): array
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->for(
            Profile::factory()->create(['user_id' => $user->id])
        )->create();

        return [$user, $driver];
    }

    private function attachMember(User $user, Driver $driver, Group $group, string $role): void
    {
        $group->members()->attach($driver->id, ['role' => $role]);
    }

    public function test_guest_is_redirected_from_all_race_routes(): void
    {
        $race = Race::factory()->create();

        $this->get(route('races'))->assertRedirect(route('login'));
        $this->get(route('races.new'))->assertRedirect(route('login'));
        $this->post(route('races.store'))->assertRedirect(route('login'));
        $this->get(route('races.show', $race))->assertRedirect(route('login'));
    }

    public function test_guest_session_actions_redirect_to_login(): void
    {
        $race = Race::factory()->create();

        $this->post(route('races.lobby.open', $race))->assertRedirect(route('login'));
        $this->post(route('races.qualifying.start', $race))->assertRedirect(route('login'));
        $this->post(route('races.qualifying.record', $race))->assertRedirect(route('login'));
        $this->post(route('races.lock-grid', $race))->assertRedirect(route('login'));
    }

    public function test_index_lists_races_for_member(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('races'))
            ->assertOk()
            ->assertSee('Races')
            ->assertSee('Opening Sprint')
            ->assertSee('Champions Finale')
            ->assertSee('Nashik Karting Arena')
            ->assertSee('Create race');
    }

    public function test_index_hides_races_of_foreign_groups(): void
    {
        $user = $this->demoActor();
        [, $stranger] = $this->userWithDriver();
        $foreignGroup = Group::factory()->create(['created_by' => $stranger->id]);
        $this->attachMember($user, $stranger, $foreignGroup, 'admin');
        Race::factory()->create(['group_id' => $foreignGroup->id, 'name' => 'Sekrit Grand Prix']);

        $this->actingAs($user)
            ->get(route('races'))
            ->assertOk()
            ->assertDontSee('Sekrit Grand Prix');
    }

    public function test_index_searches_races(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('races', ['q' => 'Monsoon']))
            ->assertOk()
            ->assertSee('Monsoon Special')
            ->assertDontSee('Opening Sprint');
    }

    public function test_index_filters_races_by_status(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('races', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Autumn Sprint')
            ->assertDontSee('Opening Sprint');
    }

    public function test_show_renders_completed_race_results_for_member(): void
    {
        $user = $this->demoActor();
        $race = $this->seededRace('Opening Sprint');

        $this->actingAs($user)
            ->get(route('races.show', $race).'?tab=results')
            ->assertOk()
            ->assertSee('Opening Sprint')
            ->assertSee('Winner')
            ->assertSee('Bilal Darji')
            ->assertSee('Results');
    }

    public function test_show_guard_falls_back_to_overview_when_tab_not_enabled(): void
    {
        $user = $this->demoActor();
        $race = $this->seededRace('Autumn Sprint');

        $this->actingAs($user)
            ->get(route('races.show', $race).'?tab=control')
            ->assertOk()
            ->assertSee('Session log');
    }

    public function test_show_denied_for_non_member(): void
    {
        $this->seed();
        [$user, $driver] = $this->userWithDriver();
        $group = Group::factory()->create(['created_by' => $driver->id]);
        $this->attachMember($user, $driver, $group, 'admin');
        $race = Race::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->get(route('races.show', $this->seededRace('Opening Sprint')))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('races.show', $race))
            ->assertOk();
    }

    public function test_create_form_lists_managed_groups_only(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('races.new'))
            ->assertOk()
            ->assertSee('Karting Crew')
            ->assertSee('Set up a race');
    }

    public function test_create_form_exposes_required_qualifying_lap_count_field(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('races.new'))
            ->assertOk()
            ->assertSee('Qualifying lap count')
            ->assertSee('name="qualifying_lap_count"', false);
    }

    public function test_store_creates_draft_race_with_organizer(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrewGroup();

        // Payload mirrors exactly the fields rendered by races/create.blade.php.
        $this->actingAs($user)
            ->post(route('races.store'), [
                'group_id' => $group->id,
                'name' => 'Winter Classic',
                'venue_name' => 'Nashik Karting Arena',
                'date' => '2027-01-09',
                'start_time' => '16:00',
                'format' => 'sprint',
                'qualifying_lap_count' => 2,
            ])
            ->assertRedirect();

        $race = Race::where('name', 'Winter Classic')->firstOrFail();
        $this->assertSame('draft', $race->status->value);
        $this->assertSame($user->driver->id, $race->organizer_id);
        $this->assertSame(2, $race->qualifying_lap_count);
        $this->assertSame('', $race->rules);
    }

    public function test_store_requires_qualifying_lap_count(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrewGroup();

        $this->actingAs($user)
            ->post(route('races.store'), [
                'group_id' => $group->id,
                'name' => 'No Laps GP',
                'venue_name' => 'Nashik Karting Arena',
                'date' => '2027-01-09',
                'start_time' => '16:00',
                'format' => 'sprint',
            ])
            ->assertSessionHasErrors('qualifying_lap_count');

        $this->assertDatabaseMissing('races', ['name' => 'No Laps GP']);
    }

    public function test_store_requires_organizer_role(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $this->kartingCrewGroup();
        $this->attachMember($user, $memberDriver, $group, 'member');

        $this->actingAs($member)
            ->post(route('races.store'), [
                'group_id' => $group->id,
                'name' => 'Not Allowed GP',
                'venue_name' => 'X',
                'date' => '2027-01-09',
                'start_time' => '16:00',
                'format' => 'sprint',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('races', ['name' => 'Not Allowed GP']);
    }

    public function test_store_requires_valid_group_id(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('races.store'), [
                'group_id' => 9999,
                'name' => 'Ghost Group GP',
                'venue_name' => 'X',
                'date' => '2027-01-09',
                'start_time' => '16:00',
                'format' => 'sprint',
            ])
            ->assertNotFound();
    }

    public function test_update_requires_organizer_role(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $this->kartingCrewGroup();
        $this->attachMember($user, $memberDriver, $group, 'member');
        $race = $this->seededRace('Autumn Sprint');

        $this->actingAs($member)
            ->patch(route('races.update', $race), [
                'name' => 'Hijacked',
                'venue_name' => 'Hacked Arena',
                'date' => '2027-01-01',
                'start_time' => '12:00',
                'format' => 'sprint',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $race->refresh()->name);
    }

    public function test_full_session_flow_lobby_to_completed(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrewGroup();

        $this->actingAs($user)
            ->post(route('races.store'), [
                'group_id' => $group->id,
                'name' => 'Session Flow GP',
                'venue_name' => 'Pune Race Zone',
                'date' => '2027-02-13',
                'start_time' => '15:00',
                'format' => 'sprint',
                'qualifying_lap_count' => 1,
            ]);

        $race = Race::where('name', 'Session Flow GP')->firstOrFail();

        // Draft → Lobby
        $this->post(route('races.lobby.open', $race))->assertRedirect();
        $this->assertSame('lobby', $race->refresh()->status->value);

        // Add two drivers to the field.
        $memberIds = $group->members()->take(2)->pluck('drivers.id')->all();
        $this->post(route('races.entries.set', $race), ['driver_ids' => $memberIds])->assertRedirect();
        $this->assertSame(2, $race->entries()->count());

        $drivers = $group->members()->orderBy('drivers.id')->take(2)->get();
        // Confirm + ready both entries.
        foreach ($drivers as $member) {
            $this->post(route('races.entries.update', ['race' => $race, 'driver' => $member->id]), ['confirmed' => 1])->assertRedirect();
            $this->post(route('races.entries.ready', ['race' => $race, 'driver' => $member->id]))->assertRedirect();
        }

        // Lobby → Qualifying
        $this->post(route('races.qualifying.start', $race))->assertRedirect();
        $this->assertSame('qualifying', $race->refresh()->status->value);

        // Record a lap per driver.
        foreach ($members = $group->members()->orderBy('drivers.id')->take(2)->get() as $i => $member) {
            $this->post(route('races.qualifying.record', $race), [
                'driver_id' => $member->id,
                'action' => 'record',
                'split_min' => 0,
                'split_sec' => 32 + $i,
                'split_ms' => 500,
            ])->assertRedirect();
        }

        // Qualifying → Grid
        $this->post(route('races.lock-grid', $race))->assertRedirect();
        $this->assertSame('grid', $race->refresh()->status->value);
        $this->assertDatabaseHas('race_entries', ['race_id' => $race->id, 'grid_position' => 1]);

        // Grid → Racing
        $this->post(route('races.start', $race))->assertRedirect();
        $this->assertSame('racing', $race->refresh()->status->value);

        // Classify finishers.
        foreach ($members as $i => $member) {
            $this->post(route('races.driver-status', ['race' => $race, 'driver' => $member->id]), ['status' => 'finished'])->assertRedirect();
        }

        // Racing → Completed
        $this->post(route('races.complete', $race))->assertRedirect();
        $this->assertSame('completed', $race->refresh()->status->value);

        // Events were recorded throughout the session.
        $this->assertGreaterThanOrEqual(5, RaceEvent::where('race_id', $race->id)->count());
        $this->assertSame(1, $race->entries()->where('finish_position', 1)->value('finish_position'));
    }

    public function test_full_session_flow_requires_manage_rights(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $this->kartingCrewGroup();
        $this->attachMember($user, $memberDriver, $group, 'member');
        $race = Race::factory()->create(['group_id' => $group->id, 'status' => 'draft']);

        $this->actingAs($member)
            ->post(route('races.lobby.open', $race))
            ->assertForbidden();

        $this->assertSame('draft', $race->refresh()->status->value);
    }

    public function test_cancel_moves_draft_to_cancelled(): void
    {
        $user = $this->demoActor();
        $race = $this->seededRace('Autumn Sprint');

        $this->actingAs($user)
            ->post(route('races.cancel', $race))
            ->assertRedirect();

        $this->assertSame('cancelled', $race->refresh()->status->value);
    }

    public function test_completed_race_cannot_be_cancelled(): void
    {
        $user = $this->demoActor();
        $race = $this->seededRace('Opening Sprint');

        $this->actingAs($user)
            ->post(route('races.cancel', $race))
            ->assertRedirect();

        $this->assertSame('completed', $race->refresh()->status->value);
    }

    public function test_penalty_issue_and_cancel_update_totals(): void
    {
        $user = $this->demoActor();
        $race = $this->seededRace('Opening Sprint');
        $entry = $race->entries()->first();

        $this->actingAs($user)
            ->post(route('races.penalties.store', $race), [
                'driver_id' => $entry->driver_id,
                'seconds' => 10,
                'reason' => 'Jump start',
            ])
            ->assertRedirect();

        $this->assertSame(10, $entry->refresh()->penalty_total_seconds);

        $penalty = $race->penalties()->firstOrFail();
        $this->actingAs($user)
            ->post(route('races.penalties.cancel', ['race' => $race, 'penalty' => $penalty]))
            ->assertRedirect();

        $this->assertSame('cancelled', $penalty->refresh()->status->value);
        $this->assertSame(0, $entry->refresh()->penalty_total_seconds);
    }

    public function test_destroy_removes_race(): void
    {
        $user = $this->demoActor();
        $race = $this->seededRace('Autumn Sprint');

        $this->actingAs($user)
            ->delete(route('races.destroy', $race))
            ->assertRedirect(route('races'));

        $this->assertDatabaseMissing('races', ['id' => $race->id]);
    }
}
