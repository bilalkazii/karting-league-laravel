<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use App\Enums\GroupRole;
use App\Models\Driver;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupsTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    public function test_index_shows_users_groups(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('groups'))
            ->assertOk()
            ->assertSee('Karting Crew')
            ->assertSee('Weekend Racers');
    }

    public function test_show_group_renders_for_member(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('Karting Crew')
            ->assertSee('Bilal Darji');
    }

    public function test_show_group_returns_403_for_non_member(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $foreignGroup = Group::factory()->create(['created_by' => $stranger->id]);

        $this->actingAs($user)
            ->get(route('groups.show', $foreignGroup))
            ->assertForbidden();
    }

    public function test_create_group_creates_group_and_admin_membership(): void
    {
        $user = $this->demoActor();

        $response = $this->actingAs($user)->post(route('groups.store'), [
            'name' => 'Midnight Racers',
            'description' => 'Late night lap chasing.',
        ]);

        $group = Group::where('name', 'Midnight Racers')->firstOrFail();

        $response->assertRedirect(route('groups.show', $group));

        $pivot = $group->members()->where('driver_id', $user->driver->id)->firstOrFail()->pivot;

        $this->assertSame($user->driver->id, $group->created_by);
        $this->assertSame(GroupRole::Admin->value, $pivot->role);
    }

    public function test_members_page_shows_member_list(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->get(route('groups.members', $group))
            ->assertOk()
            ->assertSee('Bilal Darji')
            ->assertSee('Ahmad Rehman')
            ->assertSee('Umar Khan');
    }

    public function test_update_availability_changes_pivot_value(): void
    {
        $user = $this->demoActor();
        $group = $user->driver->groups()->where('name', 'Karting Crew')->firstOrFail();

        $this->actingAs($user)
            ->from(route('groups.show', $group))
            ->post(route('groups.availability.update', $group), [
                'availability' => DriverAvailability::Maybe->value,
            ])
            ->assertRedirect(route('groups.show', $group));

        $pivot = $group->members()->where('driver_id', $user->driver->id)->firstOrFail()->pivot;

        $this->assertSame(DriverAvailability::Maybe->value, $pivot->availability);
    }
}