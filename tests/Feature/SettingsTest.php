<?php

namespace Tests\Feature;

use App\Enums\DriverProfileVisibility;
use App\Enums\NotificationType;
use App\Enums\RaceStatus;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use App\Models\User;
use App\Services\RaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    private function kartingCrew(): Group
    {
        return Group::where('name', 'Karting Crew')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        $notifications = [];
        foreach (NotificationType::cases() as $type) {
            $notifications[$type->value] = '1';
        }

        return array_merge([
            'driver_profile_visibility' => DriverProfileVisibility::Public->value,
            'notifications' => $notifications,
        ], $overrides);
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get(route('settings'))->assertRedirect(route('login'));
        $this->post(route('settings'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_settings_page_renders_preference_defaults(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Privacy')
            ->assertSee('Race opened')
            ->assertSee('Everyone signed in');
    }

    public function test_update_persists_notification_and_privacy_preferences(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('settings'), $this->payload([
                'driver_profile_visibility' => DriverProfileVisibility::Members->value,
                'notifications' => [
                    NotificationType::RaceOpened->value => '0',
                    NotificationType::RaceCompleted->value => '1',
                    NotificationType::PenaltyIssued->value => '1',
                ],
            ]))
            ->assertRedirect(route('settings'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertFalse($user->notificationEnabled(NotificationType::RaceOpened));
        $this->assertTrue($user->notificationEnabled(NotificationType::RaceCompleted));
        $this->assertTrue($user->notificationEnabled(NotificationType::PenaltyIssued));
        $this->assertSame(DriverProfileVisibility::Members, $user->driverProfileVisibility());

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => NotificationType::RaceOpened->preferenceKey(),
            'value' => '0',
        ]);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => DriverProfileVisibility::preferenceKey(),
            'value' => DriverProfileVisibility::Members->value,
        ]);
    }

    public function test_update_is_scoped_to_the_authenticated_user(): void
    {
        $user = $this->demoActor();
        $other = User::where('email', 'drv2@karting.app')->firstOrFail();

        $this->actingAs($user)->post(route('settings'), $this->payload([
            'user_id' => $other->id,
            'notifications' => [
                NotificationType::RaceOpened->value => '0',
                NotificationType::RaceCompleted->value => '1',
                NotificationType::PenaltyIssued->value => '1',
            ],
        ]))->assertRedirect(route('settings'));

        $this->assertSame(4, $user->preferences()->count());
        $this->assertSame(0, $other->preferences()->count());
        $this->assertTrue($other->notificationEnabled(NotificationType::RaceOpened));
    }

    public function test_update_drops_unknown_preference_keys(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)->post(route('settings'), $this->payload([
            'is_admin' => '1',
            'notifications' => [
                NotificationType::RaceOpened->value => '1',
                NotificationType::RaceCompleted->value => '1',
                NotificationType::PenaltyIssued->value => '1',
                'role' => '1',
            ],
        ]))->assertRedirect(route('settings'));

        $this->assertDatabaseMissing('user_preferences', ['user_id' => $user->id, 'key' => 'role']);
        $this->assertDatabaseMissing('user_preferences', ['user_id' => $user->id, 'key' => 'is_admin']);
    }

    public function test_update_rejects_invalid_visibility(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('settings'), $this->payload([
                'driver_profile_visibility' => 'friends',
            ]))
            ->assertSessionHasErrors('driver_profile_visibility');
    }

    public function test_update_requires_known_notification_keys(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('settings'), [
                'driver_profile_visibility' => DriverProfileVisibility::Public->value,
                'notifications' => [NotificationType::RaceOpened->value => '1'],
            ])
            ->assertSessionHasErrors('notifications.race_completed');
    }

    public function test_muting_race_opened_suppresses_only_that_recipient(): void
    {
        $user = $this->demoActor();
        $muted = User::where('email', 'drv3@karting.app')->firstOrFail();
        $muted->setPreference(NotificationType::RaceOpened->preferenceKey(), '0');

        $race = Race::factory()->create([
            'group_id' => $this->kartingCrew()->id,
            'name' => 'Muted Sprint',
            'status' => RaceStatus::Draft->value,
            'organizer_id' => $user->driver->id,
        ]);

        app(RaceService::class)->openLobby($race);

        $this->assertSame(0, $muted->unreadNotifications()->count());
        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_muting_race_completed_suppresses_only_that_recipient(): void
    {
        $user = $this->demoActor();
        $muted = User::where('email', 'drv3@karting.app')->firstOrFail();
        $notified = User::where('email', 'drv4@karting.app')->firstOrFail();
        $muted->setPreference(NotificationType::RaceCompleted->preferenceKey(), '0');

        $race = Race::factory()->create([
            'group_id' => $this->kartingCrew()->id,
            'name' => 'Muted Finish',
            'status' => RaceStatus::Draft->value,
            'organizer_id' => $user->driver->id,
        ]);

        $service = app(RaceService::class);
        $service->setParticipants($race, [$muted->driver->id, $notified->driver->id]);
        $race->update(['status' => RaceStatus::Racing->value]);
        $this->assertTrue($service->completeRace($race));

        $this->assertSame(0, $muted->unreadNotifications()->count());
        $this->assertSame(1, $notified->unreadNotifications()->count());
    }

    public function test_muting_penalty_suppresses_the_target_notification(): void
    {
        $user = $this->demoActor();
        $muted = User::where('email', 'drv3@karting.app')->firstOrFail();
        $muted->setPreference(NotificationType::PenaltyIssued->preferenceKey(), '0');

        $race = Race::factory()->create([
            'group_id' => $this->kartingCrew()->id,
            'name' => 'Muted Penalty',
            'status' => RaceStatus::Draft->value,
            'organizer_id' => $user->driver->id,
        ]);

        $service = app(RaceService::class);
        $service->setParticipants($race, [$muted->driver->id]);
        $this->assertTrue($service->issuePenalty($race, $muted->driver->id, 5, 'Track limits', $user->driver->id));

        $this->assertSame(0, $muted->unreadNotifications()->count());
    }

    public function test_members_only_driver_is_hidden_from_directory_and_profile(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $stranger->profile->user->setPreference(
            DriverProfileVisibility::preferenceKey(),
            DriverProfileVisibility::Members->value,
        );

        $this->actingAs($user)
            ->get(route('drivers'))
            ->assertOk()
            ->assertDontSee($stranger->profile->full_name);

        $this->actingAs($user)
            ->get(route('drivers.show', $stranger))
            ->assertForbidden();
    }

    public function test_members_only_driver_is_visible_to_shared_group_viewer(): void
    {
        $user = $this->demoActor();

        $stranger = Driver::factory()->create();
        $stranger->profile->user->setPreference(
            DriverProfileVisibility::preferenceKey(),
            DriverProfileVisibility::Members->value,
        );
        $this->kartingCrew()->members()->attach($stranger->id, ['role' => 'member']);

        $this->actingAs($user)
            ->get(route('drivers.show', $stranger))
            ->assertOk()
            ->assertSee($stranger->profile->full_name);
    }

    public function test_members_only_driver_can_always_view_own_profile(): void
    {
        $user = $this->demoActor();
        $driver = $user->driver;

        $user->setPreference(
            DriverProfileVisibility::preferenceKey(),
            DriverProfileVisibility::Members->value,
        );

        $this->actingAs($user)
            ->get(route('drivers.show', $driver))
            ->assertOk();
    }
}
