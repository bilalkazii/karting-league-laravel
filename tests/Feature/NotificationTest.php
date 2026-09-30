<?php

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Group;
use App\Models\Race;
use App\Models\User;
use App\Notifications\PenaltyIssued;
use App\Notifications\RaceCompleted;
use App\Notifications\RaceOpened;
use App\Services\RaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    private function draftRace(): Race
    {
        return Race::factory()->create([
            'group_id' => $this->kartingCrew()->id,
            'name' => 'Notification Sprint',
            'status' => RaceStatus::Draft->value,
            'organizer_id' => 1,
        ]);
    }

    public function test_guest_is_redirected_from_notifications(): void
    {
        $this->get(route('notifications'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    public function test_index_lists_users_notifications(): void
    {
        $user = $this->demoActor();
        $race = $this->draftRace();
        $user->notify(new RaceOpened($race));

        $this->actingAs($user)
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee('Race opened')
            ->assertSee('Notification Sprint')
            ->assertSee('Mark all as read');
    }

    public function test_mark_read_marks_single_notification(): void
    {
        $user = $this->demoActor();
        $race = $this->draftRace();
        $user->notify(new RaceOpened($race));
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_marks_every_notification(): void
    {
        $user = $this->demoActor();
        $race = $this->draftRace();
        $user->notify(new RaceOpened($race));
        $user->notify(new RaceCompleted($race));

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications'));

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $owner = $this->demoActor();
        $other = User::findOrFail(2);
        $race = $this->draftRace();
        $owner->notify(new RaceOpened($race));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($other)
            ->post(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_open_lobby_notifies_all_group_members(): void
    {
        $this->demoActor();
        $race = $this->draftRace();

        app(RaceService::class)->openLobby($race);

        foreach (range(1, 8) as $id) {
            $user = User::findOrFail($id);
            $this->assertSame(1, $user->unreadNotifications()->count());
            $this->assertSame(
                RaceOpened::class,
                $user->unreadNotifications()->firstOrFail()->type
            );
        }
    }

    public function test_complete_race_notifies_entry_drivers(): void
    {
        $this->demoActor();
        $group = $this->kartingCrew();
        $race = Race::factory()->create([
            'group_id' => $group->id,
            'name' => 'Racing Sprint',
            'status' => RaceStatus::Racing->value,
            'organizer_id' => 1,
        ]);
        $service = app(RaceService::class);
        $service->setParticipants($race, [3, 4]);

        $this->assertTrue($service->completeRace($race));

        foreach ([3, 4] as $id) {
            $user = User::findOrFail($id);
            $this->assertSame(1, $user->unreadNotifications()->count());
            $this->assertSame(
                RaceCompleted::class,
                $user->unreadNotifications()->firstOrFail()->type
            );
        }

        $this->assertSame(0, User::findOrFail(5)->unreadNotifications()->count());
    }

    public function test_issue_penalty_notifies_target_driver(): void
    {
        $this->demoActor();
        $race = $this->draftRace();
        $service = app(RaceService::class);
        $service->setParticipants($race, [3]);

        $this->assertTrue($service->issuePenalty($race, 3, 5, 'Track limits abuse', 1));

        $target = User::findOrFail(3);
        $this->assertSame(1, $target->unreadNotifications()->count());
        $this->assertSame(PenaltyIssued::class, $target->unreadNotifications()->firstOrFail()->type);

        $this->assertSame(0, User::findOrFail(4)->unreadNotifications()->count());
    }
}
