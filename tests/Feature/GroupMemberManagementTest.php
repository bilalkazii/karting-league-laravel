<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use App\Enums\GroupRole;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::findOrFail(1);
    }

    private function kartingCrew(): Group
    {
        return Group::where('name', 'Karting Crew')->firstOrFail();
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

    public function test_guest_is_redirected_from_member_management_routes(): void
    {
        $this->demoActor();
        $group = $this->kartingCrew();

        $this->post(route('groups.members.store', $group))->assertRedirect(route('login'));
        $this->patch(route('groups.members.role', [$group, 3]))->assertRedirect(route('login'));
        $this->delete(route('groups.members.destroy', [$group, 3]))->assertRedirect(route('login'));
    }

    public function test_admin_can_add_member(): void
    {
        $user = $this->demoActor();
        [, $newDriver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('groups.members.store', $group), ['driver_id' => $newDriver->id])
            ->assertRedirect(route('groups.members', $group));

        $pivot = $group->members()->where('driver_id', $newDriver->id)->firstOrFail()->pivot;

        $this->assertSame(GroupRole::Member->value, $pivot->role);
        $this->assertSame(DriverAvailability::Available->value, $pivot->availability);
    }

    public function test_adding_existing_member_is_rejected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('groups.members.store', $group), ['driver_id' => 3])
            ->assertStatus(422);
    }

    public function test_organizer_can_add_member(): void
    {
        $user = $this->demoActor();
        $organizer = User::findOrFail(2);
        [, $newDriver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $this->actingAs($organizer)
            ->post(route('groups.members.store', $group), ['driver_id' => $newDriver->id])
            ->assertRedirect(route('groups.members', $group));

        $this->assertTrue($group->members()->where('driver_id', $newDriver->id)->exists());
    }

    public function test_plain_member_cannot_add_member(): void
    {
        $user = $this->demoActor();
        $member = User::findOrFail(3);
        [, $newDriver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $this->actingAs($member)
            ->post(route('groups.members.store', $group), ['driver_id' => $newDriver->id])
            ->assertForbidden();

        $this->assertFalse($group->members()->where('driver_id', $newDriver->id)->exists());
    }

    public function test_admin_can_change_member_role(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->patch(route('groups.members.role', [$group, 3]), ['role' => GroupRole::Organizer->value])
            ->assertRedirect(route('groups.members', $group));

        $pivot = $group->members()->where('driver_id', 3)->firstOrFail()->pivot;

        $this->assertSame(GroupRole::Organizer->value, $pivot->role);
    }

    public function test_organizer_cannot_change_roles(): void
    {
        $user = $this->demoActor();
        $organizer = User::findOrFail(2);
        $group = $this->kartingCrew();

        $this->actingAs($organizer)
            ->patch(route('groups.members.role', [$group, 3]), ['role' => GroupRole::Admin->value])
            ->assertForbidden();

        $pivot = $group->members()->where('driver_id', 3)->firstOrFail()->pivot;
        $this->assertSame(GroupRole::Member->value, $pivot->role);
    }

    public function test_cannot_demote_last_admin(): void
    {
        $user = $this->demoActor();
        $group = Group::factory()->create(['created_by' => $user->driver->id]);
        $group->members()->attach($user->driver->id, ['role' => GroupRole::Admin->value]);

        $this->actingAs($user)
            ->patch(route('groups.members.role', [$group, $user->driver]), ['role' => GroupRole::Member->value])
            ->assertStatus(422);

        $pivot = $group->members()->where('driver_id', $user->driver->id)->firstOrFail()->pivot;
        $this->assertSame(GroupRole::Admin->value, $pivot->role);
    }

    public function test_admin_can_demote_self_when_another_admin_exists(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->patch(route('groups.members.role', [$group, $user->driver]), ['role' => GroupRole::Member->value])
            ->assertRedirect(route('groups.members', $group));

        $pivot = $group->members()->where('driver_id', $user->driver->id)->firstOrFail()->pivot;
        $this->assertSame(GroupRole::Member->value, $pivot->role);
    }

    public function test_no_penalty_for_foreign_driver(): void
    {
        $user = $this->demoActor();
        [, $stranger] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->patch(route('groups.members.role', [$group, $stranger]), ['role' => GroupRole::Member->value])
            ->assertNotFound();
    }

    public function test_admin_can_remove_member(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->delete(route('groups.members.destroy', [$group, 3]))
            ->assertRedirect(route('groups.members', $group));

        $this->assertFalse($group->members()->where('driver_id', 3)->exists());
    }

    public function test_cannot_remove_last_admin(): void
    {
        $user = $this->demoActor();
        $group = Group::factory()->create(['created_by' => $user->driver->id]);
        $group->members()->attach($user->driver->id, ['role' => GroupRole::Admin->value]);

        $this->actingAs($user)
            ->delete(route('groups.members.destroy', [$group, $user->driver]))
            ->assertStatus(422);

        $this->assertTrue($group->members()->where('driver_id', $user->driver->id)->exists());
    }

    public function test_plain_member_cannot_remove_member(): void
    {
        $this->demoActor();
        $member = User::findOrFail(3);
        $group = $this->kartingCrew();

        $this->actingAs($member)
            ->delete(route('groups.members.destroy', [$group, 4]))
            ->assertForbidden();

        $this->assertTrue($group->members()->where('driver_id', 4)->exists());
    }
}
