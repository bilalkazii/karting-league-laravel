<?php

namespace Tests\Feature;

use App\Enums\GroupRole;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Race;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Support\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceEventLabelTest extends TestCase
{
    use RefreshDatabase;

    private function userWithDriver(Group $group, string $role = GroupRole::Admin->value): User
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->for(Profile::factory()->create(['user_id' => $user->id]))->create();
        $group->members()->attach($driver->id, ['role' => $role, 'availability' => 'available', 'joined_at' => now()]);

        return $user->refresh();
    }

    private function completedRace(Group $group, array $overrides = []): Race
    {
        return Race::factory()->create(array_merge([
            'group_id' => $group->id,
            'status' => 'completed',
        ], $overrides));
    }

    /**
     * Give a completed race real finishers so the leaderboard actually renders
     * its event columns.
     *
     * @param  list<array{0: string, 1: int}>  $finishers  [full name, position]
     */
    private function addFinishers(Race $race, array $finishers): void
    {
        foreach ($finishers as $index => [$name, $position]) {
            $driver = Driver::factory()->for(Profile::factory()->create(['full_name' => $name]))->create();
            $race->entries()->create([
                'driver_id' => $driver->id,
                'kart_number' => $index + 1,
                'status' => 'finished',
                'confirmed' => true,
                'ready' => true,
                'finish_position' => $position,
            ]);
        }
    }

    public function test_event_label_is_null_by_default_so_existing_races_are_untouched(): void
    {
        $race = $this->completedRace(Group::factory()->create());

        $this->assertNull($race->fresh()->event_label);
    }

    public function test_leaderboard_shows_the_event_label_instead_of_the_race_name(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Event Cup',
            'status' => 'active',
        ]);

        $race = $this->completedRace($group, ['name' => 'Internal Race Name', 'event_label' => 'PITSTOP']);
        $season->races()->attach($race->id, ['round_number' => 1]);
        $this->addFinishers($race, [['P1', 1], ['P2', 2]]);

        $response = $this->actingAs($user)->get(route('championship.show', $season));

        $response->assertOk()->assertSee('PITSTOP');

        $events = $response->viewData('events');
        $this->assertSame('PITSTOP', $events[0]['name']);
        $this->assertSame('PITSTOP', $events[0]['event_label']);
        $this->assertSame('Internal Race Name', $events[0]['race_name']);
    }

    public function test_race_without_a_label_still_shows_its_name(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Mixed Cup',
            'status' => 'active',
        ]);

        $labelled = $this->completedRace($group, ['name' => 'Round One', 'event_label' => 'VIRAJ']);
        $unlabelled = $this->completedRace($group, ['name' => 'Round Two']);
        $season->races()->attach($labelled->id, ['round_number' => 1]);
        $season->races()->attach($unlabelled->id, ['round_number' => 2]);
        $this->addFinishers($labelled, [['P1', 1]]);
        $this->addFinishers($unlabelled, [['P2', 1]]);

        $response = $this->actingAs($user)->get(route('championship.show', $season));

        $response->assertOk()->assertSee('VIRAJ');

        $events = collect($response->viewData('events'))->keyBy('race_id');

        $this->assertSame('VIRAJ', $events[$labelled->id]['name']);
        $this->assertNull($events[$unlabelled->id]['event_label']);
        $this->assertSame('Round Two', $events[$unlabelled->id]['name']);
    }

    public function test_a_championship_is_not_limited_to_three_events(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Long Championship',
            'status' => 'active',
        ]);

        $labels = ['PITSTOP', 'VIRAJ', 'FNF', 'ROUND FOUR', 'ROUND FIVE'];
        foreach ($labels as $index => $label) {
            $race = $this->completedRace($group, ['name' => "Race {$index}", 'event_label' => $label]);
            $season->races()->attach($race->id, ['round_number' => $index + 1]);
            $this->addFinishers($race, [['Driver '.$index, 1]]);
        }

        $response = $this->actingAs($user)->get(route('championship.show', $season));
        $response->assertOk();

        $events = $response->viewData('events');

        $this->assertCount(5, $events);
        $this->assertSame($labels, array_column($events, 'name'));
    }

    public function test_admin_can_set_and_clear_an_event_label_on_a_completed_race(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);
        $race = $this->completedRace($group);

        $this->actingAs($user)
            ->from(route('races.show', $race))
            ->patch(route('races.event-label.update', $race), ['event_label' => 'FNF'])
            ->assertRedirect(route('races.show', $race))
            ->assertSessionHas('status', 'Event label set to FNF.');

        $this->assertSame('FNF', $race->fresh()->event_label);

        $this->actingAs($user)
            ->from(route('races.show', $race))
            ->patch(route('races.event-label.update', $race), ['event_label' => ''])
            ->assertRedirect(route('races.show', $race))
            ->assertSessionHas('status', 'Event label cleared.');

        $this->assertNull($race->fresh()->event_label);
    }

    public function test_changing_an_event_label_never_touches_results(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);

        $race = $this->completedRace($group);
        $driver = Driver::factory()->for(Profile::factory()->create(['full_name' => 'Shoaib Khan']))->create();
        $race->entries()->create([
            'driver_id' => $driver->id,
            'kart_number' => 7,
            'status' => 'finished',
            'confirmed' => true,
            'ready' => true,
            'finish_position' => 1,
        ]);

        $before = StandingsService::computeStandings([$race], StandingsService::scoringFor(Season::factory()->create()))['final'];

        $this->actingAs($user)
            ->patch(route('races.event-label.update', $race), ['event_label' => 'PITSTOP'])
            ->assertRedirect();

        $entry = $race->fresh()->entries()->first();
        $this->assertSame(1, $entry->finish_position);
        $this->assertSame('finished', $entry->status->value);
        $this->assertSame('Shoaib Khan', $entry->driver->profile->full_name);

        $after = StandingsService::computeStandings([$race->fresh()], StandingsService::scoringFor(Season::factory()->create()))['final'];
        $this->assertEquals(
            collect($before)->pluck('points', 'driver_id')->all(),
            collect($after)->pluck('points', 'driver_id')->all(),
        );
    }

    public function test_ordinary_member_cannot_set_an_event_label(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group, GroupRole::Member->value);
        $race = $this->completedRace($group);

        $this->actingAs($user)
            ->patch(route('races.event-label.update', $race), ['event_label' => 'VIRAJ'])
            ->assertForbidden();

        $this->assertNull($race->fresh()->event_label);
    }

    public function test_organizer_can_set_an_event_label(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group, GroupRole::Organizer->value);
        $race = $this->completedRace($group);

        $this->actingAs($user)
            ->patch(route('races.event-label.update', $race), ['event_label' => 'PITSTOP'])
            ->assertRedirect();

        $this->assertSame('PITSTOP', $race->fresh()->event_label);
    }

    public function test_event_label_is_validated(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);
        $race = $this->completedRace($group);

        $this->actingAs($user)
            ->from(route('races.show', $race))
            ->patch(route('races.event-label.update', $race), ['event_label' => str_repeat('X', 40)])
            ->assertSessionHasErrors('event_label');

        $this->assertNull($race->fresh()->event_label);
    }

    public function test_a_race_in_a_different_group_cannot_be_labelled(): void
    {
        $group = Group::factory()->create();
        $otherGroup = Group::factory()->create();
        $user = $this->userWithDriver($otherGroup);
        $race = $this->completedRace($group);

        $this->actingAs($user)
            ->patch(route('races.event-label.update', $race), ['event_label' => 'FNF'])
            ->assertForbidden();
    }

    public function test_teams_are_unaffected_by_labels_but_still_sum_member_points(): void
    {
        $group = Group::factory()->create();
        $user = $this->userWithDriver($group);

        $season = Season::create([
            'group_id' => $group->id,
            'name' => 'Team Cup',
            'status' => 'active',
        ]);

        $a = Driver::factory()->for(Profile::factory()->create(['full_name' => 'Driver A']))->create();
        $b = Driver::factory()->for(Profile::factory()->create(['full_name' => 'Driver B']))->create();

        $race = $this->completedRace($group, ['name' => 'Round One', 'event_label' => 'PITSTOP']);
        $season->races()->attach($race->id, ['round_number' => 1]);
        $race->entries()->create([
            'driver_id' => $a->id, 'kart_number' => 1, 'status' => 'finished',
            'confirmed' => true, 'ready' => true, 'finish_position' => 1,
        ]);
        $race->entries()->create([
            'driver_id' => $b->id, 'kart_number' => 2, 'status' => 'finished',
            'confirmed' => true, 'ready' => true, 'finish_position' => 2,
        ]);

        $team = Team::create(['group_id' => $group->id, 'name' => 'Apex Racing', 'logo_initials' => 'AR']);
        $team->members()->attach([$a->id, $b->id]);

        $response = $this->actingAs($user)->get(route('championship.show', $season));
        $response->assertOk();

        $apex = collect($response->viewData('teamStandings'))->firstWhere('name', 'Apex Racing');
        $this->assertSame(43, $apex['points']);
    }
}
