<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Season;
use App\Models\User;
use App\Support\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonsTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    private function seededSeason(): Season
    {
        return Season::where('name', 'Crew Championship 2026')->firstOrFail();
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

    public function test_guest_index_redirects_to_login(): void
    {
        $this->get(route('championship'))->assertRedirect(route('login'));
    }

    public function test_guest_show_redirects_to_login(): void
    {
        $season = Season::factory()->create();

        $this->get(route('championship.show', $season))->assertRedirect(route('login'));
    }

    public function test_guest_create_redirects_to_login(): void
    {
        $this->get(route('seasons.new'))->assertRedirect(route('login'));
    }

    public function test_guest_store_redirects_to_login(): void
    {
        $this->post(route('seasons.store'))->assertRedirect(route('login'));
    }

    public function test_guest_edit_redirects_to_login(): void
    {
        $season = Season::factory()->create();

        $this->get(route('seasons.edit', $season))->assertRedirect(route('login'));
    }

    public function test_guest_update_redirects_to_login(): void
    {
        $season = Season::factory()->create();

        $this->patch(route('seasons.update', $season), ['name' => 'Changed'])->assertRedirect(route('login'));
    }

    public function test_guest_destroy_redirects_to_login(): void
    {
        $season = Season::factory()->create();

        $this->delete(route('seasons.destroy', $season))->assertRedirect(route('login'));
    }

    public function test_index_lists_seasons_across_groups_for_member(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('championship'))
            ->assertOk()
            ->assertSee('Championship')
            ->assertSee('Crew Championship 2026')
            ->assertSee('Karting Crew')
            ->assertSee('Active');
    }

    public function test_index_hides_seasons_of_groups_user_does_not_belong_to(): void
    {
        $user = $this->demoActor();
        [, $stranger] = $this->userWithDriver();
        $foreignGroup = Group::factory()->create(['created_by' => $stranger->id]);
        $foreignGroup->members()->attach($stranger->id);
        Season::factory()->create(['group_id' => $foreignGroup->id, 'name' => 'Sekrit Championship']);

        $this->actingAs($user)
            ->get(route('championship'))
            ->assertOk()
            ->assertSee('Crew Championship 2026')
            ->assertDontSee('Sekrit Championship');
    }

    public function test_index_filters_seasons_by_status(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        Season::factory()->create(['group_id' => $group->id, 'name' => 'Summer B-Series', 'status' => 'draft']);

        $this->actingAs($user)
            ->get(route('championship', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Summer B-Series')
            ->assertDontSee('Crew Championship 2026');
    }

    public function test_index_searches_seasons_by_name(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('championship', ['q' => 'Crew']))
            ->assertOk()
            ->assertSee('Crew Championship 2026');
    }

    public function test_show_renders_season_details_for_member(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('championship.show', $this->seededSeason()))
            ->assertOk()
            ->assertSee('Crew Championship 2026')
            ->assertSee('Karting Crew')
            ->assertSee('Scoring rules')
            ->assertSee('Rounds')
            ->assertSee('Opening Sprint')
            ->assertSee('Bilal Darji')
            ->assertSee('Season records');
    }

    public function test_show_denied_for_non_member(): void
    {
        $this->demoActor();
        [$outsider, $driver] = $this->userWithDriver();
        $group = Group::factory()->create(['created_by' => $driver->id]);
        $group->members()->attach($driver->id);
        $season = Season::factory()->create(['group_id' => $group->id]);

        $this->actingAs($outsider, 'web')
            ->get(route('championship.show', $this->seededSeason()))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('championship.show', $season))
            ->assertOk();
    }

    public function test_create_form_lists_managed_groups_only(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('seasons.new'))
            ->assertOk()
            ->assertSee('Karting Crew');
    }

    public function test_store_creates_season_with_default_scoring(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'group_id' => $group->id,
                'name' => 'Winter Cup 2027',
                'status' => 'draft',
                'start_date' => '2027-01-01',
                'end_date' => '2027-03-01',
            ])
            ->assertRedirect();

        $season = Season::where('name', 'Winter Cup 2027')->firstOrFail();
        $this->assertSame('draft', $season->status->value);

        // A new season awards points by finishing position only, so neither a
        // pole nor a fastest lap carries a bonus.
        $this->assertSame(0, $season->scoring->pole_position_points);
        $this->assertSame(0, $season->scoring->fastest_lap_points);
        $this->assertSame(25, $season->scoringPoints()->where('position', 1)->value('points'));
        $this->assertSame(8, $season->scoringPoints()->count());
    }

    public function test_a_new_season_awards_no_pole_position_bonus(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'group_id' => $group->id,
                'name' => 'Spring Cup 2027',
                'status' => 'draft',
                'start_date' => '2027-04-01',
                'end_date' => '2027-06-01',
            ])
            ->assertRedirect();

        $season = Season::where('name', 'Spring Cup 2027')->firstOrFail();

        $this->assertSame(0, $season->scoring->pole_position_points);
        $this->assertSame(0, StandingsService::scoringFor($season)['pole_position_points']);

        // The bonus is genuinely absent rather than merely stored as zero: the
        // driver who takes pole scores exactly what their finishing position is
        // worth, with nothing added on top.
        $race = $this->seededSeason()->races()->where('status', 'completed')->firstOrFail();
        $season->races()->attach($race->id, ['round_number' => 1]);

        $points = StandingsService::computeRacePoints(
            StandingsService::raceEntries($race->entries()->get()),
            StandingsService::scoringFor($season->fresh()),
        );

        $this->assertNotNull($points['pole_driver_id']);

        $poleRow = collect($points['entries'])
            ->firstWhere('driver_id', $points['pole_driver_id']);

        $this->assertSame(0, $poleRow['pole_points']);
        $this->assertSame($poleRow['base_points'], $poleRow['points']);

        foreach ($points['entries'] as $row) {
            $this->assertSame(0, $row['pole_points']);
        }
    }

    public function test_store_requires_organizer_role(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $group->members()->attach($memberDriver->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('seasons.store'), [
                'group_id' => $group->id,
                'name' => 'Not Allowed Cup',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('seasons', ['name' => 'Not Allowed Cup']);
    }

    public function test_store_rejects_group_user_does_not_belong_to(): void
    {
        $user = $this->demoActor();
        [, $stranger] = $this->userWithDriver();
        $foreignGroup = Group::factory()->create(['created_by' => $stranger->id]);
        $foreignGroup->members()->attach($stranger->id);

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'group_id' => $foreignGroup->id,
                'name' => 'Foreign Cup',
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    public function test_store_requires_name(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'group_id' => $group->id,
                'name' => '',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_duplicate_name_within_group(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'group_id' => $group->id,
                'name' => 'Crew Championship 2026',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'name' => 'Time Anomaly Cup',
                'status' => 'draft',
                'start_date' => '2026-06-01',
                'end_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_store_rejects_invalid_status(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'name' => 'Broken Status Cup',
                'status' => 'frozen',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_store_rejects_second_active_season_in_group(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->post(route('seasons.store'), [
                'group_id' => $group->id,
                'name' => 'Second Active Cup',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_edit_requires_organizer_role(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $group->members()->attach($memberDriver->id, ['role' => 'member']);

        $this->actingAs($member)
            ->get(route('seasons.edit', $this->seededSeason()))
            ->assertForbidden();
    }

    public function test_update_renames_season(): void
    {
        $user = $this->demoActor();
        $season = $this->seededSeason();

        $this->actingAs($user)
            ->patch(route('seasons.update', $season), [
                'name' => 'Crew Championship 2027',
                'status' => 'active',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ])
            ->assertRedirect(route('championship.show', $season));

        $this->assertSame('Crew Championship 2027', $season->refresh()->name);
        $this->assertSame('active', $season->status->value);
    }

    public function test_update_preserves_group(): void
    {
        $user = $this->demoActor();
        $season = $this->seededSeason();
        $originalGroup = $season->group_id;

        $this->actingAs($user)
            ->patch(route('seasons.update', $season), [
                'name' => 'Crew Championship 2027',
                'status' => 'active',
            ]);

        $this->assertSame($originalGroup, $season->refresh()->group_id);
    }

    public function test_update_rejects_invalid_status_transition(): void
    {
        $user = $this->demoActor();
        $season = $this->seededSeason();

        $this->actingAs($user)
            ->patch(route('seasons.update', $season), [
                'name' => $season->name,
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $season->refresh()->status->value);
    }

    public function test_update_allows_valid_status_transition(): void
    {
        $user = $this->demoActor();
        $season = $this->seededSeason();

        $this->actingAs($user)
            ->patch(route('seasons.update', $season), [
                'name' => $season->name,
                'status' => 'completed',
            ])
            ->assertRedirect();

        $this->assertSame('completed', $season->refresh()->status->value);
    }

    public function test_update_rejects_becoming_active_while_another_is_active(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $draftSeason = Season::factory()->create(['group_id' => $group->id, 'name' => 'Summer B-Series', 'status' => 'draft']);

        $this->actingAs($user)
            ->patch(route('seasons.update', $draftSeason), [
                'name' => 'Summer B-Series',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_season_can_become_active_after_current_one_completes(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $draftSeason = Season::factory()->create(['group_id' => $group->id, 'name' => 'Summer B-Series', 'status' => 'draft']);

        $this->actingAs($user)
            ->patch(route('seasons.update', $this->seededSeason()), [
                'name' => 'Crew Championship 2026',
                'status' => 'completed',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('seasons.update', $draftSeason), [
                'name' => 'Summer B-Series',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('active', $draftSeason->refresh()->status->value);
    }

    public function test_update_requires_organizer_role(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $group->members()->attach($memberDriver->id, ['role' => 'member']);
        $season = $this->seededSeason();

        $this->actingAs($member)
            ->patch(route('seasons.update', $season), [
                'name' => 'Hijacked',
                'status' => 'completed',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $season->refresh()->name);
    }

    public function test_season_with_history_cannot_be_destroyed(): void
    {
        $user = $this->demoActor();
        $season = $this->seededSeason();
        $raceIds = $season->races()->pluck('races.id');

        $this->actingAs($user)
            ->delete(route('seasons.destroy', $season))
            ->assertStatus(422);

        $this->assertDatabaseHas('seasons', ['id' => $season->id]);
        $this->assertDatabaseHas('season_scoring', ['season_id' => $season->id]);
        $this->assertDatabaseHas('season_races', ['season_id' => $season->id]);
        $this->assertDatabaseHas('awards', ['season_id' => $season->id]);
        $this->assertDatabaseHas('season_records', ['season_id' => $season->id]);

        foreach ($raceIds as $raceId) {
            $this->assertDatabaseHas('races', ['id' => $raceId]);
        }
    }

    public function test_empty_season_can_be_destroyed(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Empty Pre-Season',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete(route('seasons.destroy', $season))
            ->assertRedirect(route('championship'));

        $this->assertDatabaseMissing('seasons', ['id' => $season->id]);
    }

    public function test_destroy_requires_organizer_role(): void
    {
        $user = $this->demoActor();
        [$member, $memberDriver] = $this->userWithDriver();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $group->members()->attach($memberDriver->id, ['role' => 'member']);
        $season = $this->seededSeason();

        $this->actingAs($member)
            ->delete(route('seasons.destroy', $season))
            ->assertForbidden();

        $this->assertDatabaseHas('seasons', ['id' => $season->id]);
    }
}
