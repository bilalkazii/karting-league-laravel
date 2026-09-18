<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriversTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    public function test_guest_index_redirects_to_login(): void
    {
        $this->get(route('drivers'))->assertRedirect(route('login'));
    }

    public function test_guest_profile_redirects_to_login(): void
    {
        $driver = Driver::factory()->create();

        $this->get(route('drivers.show', $driver))->assertRedirect(route('login'));
    }

    public function test_guest_edit_redirects_to_login(): void
    {
        $driver = Driver::factory()->create();

        $this->get(route('drivers.edit', $driver))->assertRedirect(route('login'));
    }

    public function test_guest_update_redirects_to_login(): void
    {
        $driver = Driver::factory()->create();

        $this->patch(route('drivers.update', $driver), [
            'full_name' => 'Changed Name',
        ])->assertRedirect(route('login'));
    }

    public function test_index_lists_seeded_drivers(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('drivers'))
            ->assertOk()
            ->assertSee('Bilal Darji')
            ->assertSee('Zain Farooqi')
            ->assertSee('Karting Crew');
    }

    public function test_index_search_filters_drivers(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('drivers', ['q' => 'Ahmad']))
            ->assertOk()
            ->assertSee('Ahmad Rehman')
            ->assertDontSee('Zain Farooqi')
            ->assertDontSee('Arjun Mehta');
    }

    public function test_index_hides_foreign_group_from_viewer(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $foreignGroup = Group::factory()->create([
            'name' => 'Sekrit Racing Club',
            'created_by' => $stranger->id,
        ]);
        $foreignGroup->members()->attach($stranger->id);

        $this->actingAs($user)
            ->get(route('drivers'))
            ->assertOk()
            ->assertSee($stranger->profile->full_name)
            ->assertDontSee('Sekrit Racing Club');
    }

    public function test_profile_shows_stats_and_recent_results(): void
    {
        $user = $this->demoActor();
        $driver = $user->driver;

        $this->actingAs($user)
            ->get(route('drivers.show', $driver))
            ->assertOk()
            ->assertSee('Recent results')
            ->assertSee('Opening Sprint')
            ->assertSee('Starts')
            ->assertSee('Championships')
            ->assertSee('Crew Championship 2026');
    }

    public function test_profile_hides_foreign_group_from_non_member_viewer(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $foreignGroup = Group::factory()->create([
            'name' => 'Private Crew X',
            'created_by' => $stranger->id,
        ]);
        $foreignGroup->members()->attach($stranger->id);

        $this->actingAs($user)
            ->get(route('drivers.show', $stranger))
            ->assertOk()
            ->assertSee($stranger->profile->full_name)
            ->assertDontSee('Private Crew X');
    }

    public function test_profile_shows_shared_group_to_member_viewer(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $kartingCrew = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();
        $kartingCrew->members()->attach($stranger->id, ['role' => 'member']);

        $this->actingAs($user)
            ->get(route('drivers.show', $stranger))
            ->assertOk()
            ->assertSee('Karting Crew');
    }

    public function test_owner_can_edit_own_profile(): void
    {
        $user = $this->demoActor();
        $driver = $user->driver;

        $this->actingAs($user)
            ->patch(route('drivers.update', $driver), [
                'full_name' => 'Bilal Darji Jr',
                'nickname' => 'BBD',
                'racing_number' => 77,
                'avatar_color' => '#ef3340',
                'avatar_text_color' => '#ffffff',
            ])
            ->assertRedirect(route('drivers.show', $driver))
            ->assertSessionHas('status');

        $driver->refresh();

        $this->assertSame('Bilal Darji Jr', $driver->profile->full_name);
        $this->assertSame('BBD', $driver->nickname);
        $this->assertSame(77, $driver->racing_number);
        $this->assertSame('#ef3340', $driver->avatar_color);
    }

    public function test_owner_nickname_is_stored_uppercased(): void
    {
        $user = $this->demoActor();
        $driver = $user->driver;

        $this->actingAs($user)
            ->patch(route('drivers.update', $driver), [
                'full_name' => $driver->profile->full_name,
                'nickname' => 'bd',
            ]);

        $this->assertSame('BD', $driver->refresh()->nickname);
    }

    public function test_non_owner_cannot_view_edit_form(): void
    {
        $user = $this->demoActor();
        $stranger = Driver::factory()->create();

        $this->actingAs($user)
            ->get(route('drivers.edit', $stranger))
            ->assertForbidden();
    }

    public function test_non_owner_cannot_update_profile(): void
    {
        $user = $this->demoActor();
        $stranger = Driver::factory()->create();

        $this->actingAs($user)
            ->patch(route('drivers.update', $stranger), [
                'full_name' => 'Hacked Name',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hacked Name', $stranger->refresh()->profile->full_name);
    }

    public function test_update_requires_full_name(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->patch(route('drivers.update', $user->driver), ['full_name' => ''])
            ->assertSessionHasErrors('full_name');
    }

    public function test_update_rejects_invalid_racing_number(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->patch(route('drivers.update', $user->driver), [
                'full_name' => 'Bilal Darji',
                'racing_number' => 1000,
            ])
            ->assertSessionHasErrors('racing_number');
    }

    public function test_update_rejects_invalid_avatar_color(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->patch(route('drivers.update', $user->driver), [
                'full_name' => 'Bilal Darji',
                'avatar_color' => 'red',
            ])
            ->assertSessionHasErrors('avatar_color');
    }

    public function test_update_preserves_blank_optional_fields(): void
    {
        $user = $this->demoActor();
        $driver = $user->driver;

        $this->actingAs($user)
            ->patch(route('drivers.update', $driver), [
                'full_name' => 'Bilal Darji',
                'nickname' => '',
                'racing_number' => '',
            ])
            ->assertRedirect(route('drivers.show', $driver));

        $driver->refresh();

        $this->assertNull($driver->nickname);
        $this->assertNull($driver->racing_number);
    }
}
