<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Race;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChatTest extends TestCase
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

    public function test_guest_is_redirected_from_all_chat_routes(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $race = Race::factory()->create(['group_id' => $group->id, 'organizer_id' => $user->driver->id]);
        $message = ChatMessage::create(['group_id' => $group->id, 'sender_id' => $user->driver->id, 'body' => 'Hey']);

        $this->get(route('chat'))->assertRedirect(route('login'));
        $this->get(route('chat.group', $group))->assertRedirect(route('login'));
        $this->post(route('chat.group.send', $group))->assertRedirect(route('login'));
        $this->get(route('chat.race', $race))->assertRedirect(route('login'));
        $this->post(route('chat.race.send', $race))->assertRedirect(route('login'));
        $this->delete(route('chat.messages.destroy', $message))->assertRedirect(route('login'));
    }

    public function test_member_can_view_group_thread(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->get(route('chat.group', $group))
            ->assertOk()
            ->assertSee('Karting Crew');
    }

    public function test_non_member_is_forbidden_from_group_thread(): void
    {
        $this->demoActor();
        [$stranger, $strangerDriver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $this->actingAs($stranger)
            ->get(route('chat.group', $group))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('chat.group.send', $group), ['body' => 'Sneaking in'])
            ->assertForbidden();
    }

    public function test_member_can_send_group_message(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('chat.group.send', $group), ['body' => 'Lapping everyone today'])
            ->assertRedirect(route('chat.group', $group));

        $this->assertDatabaseHas('chat_messages', [
            'group_id' => $group->id,
            'sender_id' => $user->driver->id,
            'body' => 'Lapping everyone today',
        ]);
    }

    public function test_message_body_is_trimmed(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('chat.group.send', $group), ['body' => '  Clear track ahead  '])
            ->assertRedirect(route('chat.group', $group));

        $this->assertDatabaseHas('chat_messages', [
            'group_id' => $group->id,
            'body' => 'Clear track ahead',
        ]);
    }

    public function test_whitespace_only_message_is_rejected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->from(route('chat.group', $group))
            ->post(route('chat.group.send', $group), ['body' => '   '])
            ->assertRedirect(route('chat.group', $group))
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_empty_message_is_rejected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->from(route('chat.group', $group))
            ->post(route('chat.group.send', $group), ['body' => ''])
            ->assertRedirect(route('chat.group', $group))
            ->assertSessionHasErrors('body');
    }

    public function test_overlong_message_is_rejected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->from(route('chat.group', $group))
            ->post(route('chat.group.send', $group), ['body' => str_repeat('a', 501)])
            ->assertRedirect(route('chat.group', $group))
            ->assertSessionHasErrors('body');
    }

    public function test_member_can_use_race_thread(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();
        $race = Race::factory()->create(['group_id' => $group->id, 'organizer_id' => $user->driver->id]);

        $this->actingAs($user)
            ->get(route('chat.race', $race))
            ->assertOk()
            ->assertSee($race->name);

        $this->actingAs($user)
            ->post(route('chat.race.send', $race), ['body' => 'Tyre strategy anyone?'])
            ->assertRedirect(route('chat.race', $race));

        $this->assertDatabaseHas('chat_messages', [
            'race_id' => $race->id,
            'sender_id' => $user->driver->id,
            'body' => 'Tyre strategy anyone?',
        ]);
    }

    public function test_non_member_is_forbidden_from_race_thread(): void
    {
        $demo = $this->demoActor();
        [$stranger, $strangerDriver] = $this->userWithDriver();
        $group = $this->kartingCrew();
        $race = Race::factory()->create(['group_id' => $group->id, 'organizer_id' => $demo->driver->id]);

        $this->actingAs($stranger)
            ->get(route('chat.race', $race))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('chat.race.send', $race), ['body' => 'Outsider here'])
            ->assertForbidden();
    }

    public function test_sender_can_delete_own_message(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $message = app(ChatService::class)->storeMessage($user->driver, 'Delete me', $group);

        $this->actingAs($user)
            ->delete(route('chat.messages.destroy', $message))
            ->assertRedirect();

        $this->assertDatabaseMissing('chat_messages', ['id' => $message->id]);
    }

    public function test_cannot_delete_another_drivers_message(): void
    {
        $this->demoActor();
        $other = User::where('email', 'drv2@karting.app')->firstOrFail();
        $group = $this->kartingCrew();

        $message = app(ChatService::class)->storeMessage($other->driver, 'Mine', $group);

        $this->actingAs($this->demoActor())
            ->from(route('chat.group', $group))
            ->delete(route('chat.messages.destroy', $message))
            ->assertForbidden();

        $this->assertDatabaseHas('chat_messages', ['id' => $message->id]);
    }

    public function test_group_read_watermark_drives_unread_count(): void
    {
        $demo = $this->demoActor();
        $other = User::where('email', 'drv2@karting.app')->firstOrFail();
        $group = $this->kartingCrew();
        $service = app(ChatService::class);

        $service->storeMessage($other->driver, 'First word', $group);

        $this->assertSame(1, $service->unreadInGroupThread($demo->driver, $group));

        $this->actingAs($demo)
            ->get(route('chat.group', $group))
            ->assertOk();

        $this->assertSame(0, $service->unreadInGroupThread($demo->driver, $group));
        $this->assertSame(0, $service->unreadForDriver($demo->driver));
    }

    public function test_chat_index_lists_rooms_with_preview_and_unread(): void
    {
        $demo = $this->demoActor();
        $other = User::where('email', 'drv2@karting.app')->firstOrFail();
        $group = $this->kartingCrew();

        app(ChatService::class)->storeMessage($other->driver, 'Practice start at dusk', $group);

        $this->actingAs($demo)
            ->get(route('chat'))
            ->assertOk()
            ->assertSee('Karting Crew')
            ->assertSee('Practice start at dusk')
            ->assertSee('Weekend Racers');
    }

    public function test_service_rejects_message_without_a_thread(): void
    {
        $demo = $this->demoActor();
        $service = app(ChatService::class);

        $this->expectException(HttpException::class);
        $service->storeMessage($demo->driver, 'No scope');
    }

    public function test_service_rejects_message_targeting_two_threads(): void
    {
        $demo = $this->demoActor();
        $service = app(ChatService::class);
        $group = $this->kartingCrew();
        $race = Race::factory()->create(['group_id' => $group->id, 'organizer_id' => $demo->driver->id]);

        $this->expectException(HttpException::class);
        $service->storeMessage($demo->driver, 'Both scopes', $group, $race);
    }
}
