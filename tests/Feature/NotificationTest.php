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

        return User::where('email', 'demo@karting.app')->firstOrFail();
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
            'organizer_id' => User::where('email', 'demo@karting.app')->firstOrFail()->driver->id,
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
        $other = User::where('email', 'drv2@karting.app')->firstOrFail();
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
        $group = $this->kartingCrew();
        $race = $this->draftRace();

        app(RaceService::class)->openLobby($race);

        $members = $group->members()->with('profile.user')->get();
        $this->assertCount(8, $members);

        foreach ($members as $member) {
            $user = $member->profile->user;
            $this->assertSame(1, $user->unreadNotifications()->count());
            $this->assertSame(
                RaceOpened::class,
                $user->unreadNotifications()->firstOrFail()->type
            );
        }
    }

    public function test_complete_race_notifies_entry_drivers(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $race = Race::factory()->create([
            'group_id' => $group->id,
            'name' => 'Racing Sprint',
            'status' => RaceStatus::Draft->value,
            'organizer_id' => $user->driver->id,
        ]);
        $umar = User::where('email', 'drv3@karting.app')->firstOrFail();
        $arjun = User::where('email', 'drv4@karting.app')->firstOrFail();
        $zain = User::where('email', 'drv5@karting.app')->firstOrFail();
        $service = app(RaceService::class);
        $service->setParticipants($race, [$umar->driver->id, $arjun->driver->id]);
        $race->update(['status' => RaceStatus::Racing->value]);

        $this->assertTrue($service->completeRace($race));

        foreach ([$umar, $arjun] as $recipient) {
            $this->assertSame(1, $recipient->unreadNotifications()->count());
            $this->assertSame(
                RaceCompleted::class,
                $recipient->unreadNotifications()->firstOrFail()->type
            );
        }

        $this->assertSame(0, $zain->unreadNotifications()->count());
    }

    public function test_issue_penalty_notifies_target_driver(): void
    {
        $user = $this->demoActor();
        $race = $this->draftRace();
        $target = User::where('email', 'drv3@karting.app')->firstOrFail();
        $arjun = User::where('email', 'drv4@karting.app')->firstOrFail();
        $service = app(RaceService::class);
        $service->setParticipants($race, [$target->driver->id]);

        $this->assertTrue($service->issuePenalty($race, $target->driver->id, 5, 'Track limits abuse', $user->driver->id));

        $this->assertSame(1, $target->unreadNotifications()->count());
        $this->assertSame(PenaltyIssued::class, $target->unreadNotifications()->firstOrFail()->type);

        $this->assertSame(0, $arjun->unreadNotifications()->count());
    }
}
