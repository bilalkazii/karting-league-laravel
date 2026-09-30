<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use App\Enums\GroupRole;
use App\Enums\InviteStatus;
use App\Mail\GroupInvitation;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Invite;
use App\Models\Profile;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InviteTest extends TestCase
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

    private function invites(): InviteService
    {
        return app(InviteService::class);
    }

    /**
     * @return array{invite: Invite, token: string, url: string}
     */
    private function createInvite(Group $group, ?string $email = null, ?Driver $inviter = null): array
    {
        return $this->invites()->create($group, $inviter, $email);
    }

    public function test_guest_cannot_create_or_revoke_invitations(): void
    {
        $this->seed();
        $group = $this->kartingCrew();
        $invite = $this->createInvite($group)['invite'];

        $this->post(route('groups.invites.store', $group))->assertRedirect(route('login'));
        $this->delete(route('groups.invites.destroy', [$group, $invite]))->assertRedirect(route('login'));
    }

    public function test_admin_can_create_invitation_and_mail_is_sent(): void
    {
        Mail::fake();
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('groups.invites.store', $group), ['email' => 'New.Driver@Example.com'])
            ->assertRedirect(route('groups.members', $group))
            ->assertSessionHas('invite_url');

        $invite = Invite::latest('id')->firstOrFail();

        $this->assertSame($group->id, $invite->group_id);
        $this->assertSame($user->driver->id, $invite->invited_by);
        $this->assertSame('new.driver@example.com', $invite->email);
        $this->assertSame(GroupRole::Member->value, $invite->role);
        $this->assertSame(InviteStatus::Pending, $invite->status);

        Mail::assertSent(GroupInvitation::class, function (GroupInvitation $mail) use ($invite) {
            $token = Str::afterLast($mail->inviteUrl, '/');

            return $mail->hasTo('new.driver@example.com')
                && hash('sha256', $token) === $invite->token_hash;
        });
    }

    public function test_organizer_can_create_invitation(): void
    {
        Mail::fake();
        $this->demoActor();
        $organizer = User::findOrFail(2);
        $group = $this->kartingCrew();

        $this->actingAs($organizer)
            ->post(route('groups.invites.store', $group))
            ->assertRedirect(route('groups.members', $group));

        $this->assertSame(1, Invite::where('group_id', $group->id)->count());
    }

    public function test_plain_member_cannot_create_invitation(): void
    {
        Mail::fake();
        $this->demoActor();
        $member = User::findOrFail(3);
        $group = $this->kartingCrew();

        $this->actingAs($member)
            ->post(route('groups.invites.store', $group))
            ->assertForbidden();

        $this->assertSame(0, Invite::count());
    }

    public function test_cross_group_invite_management_is_denied(): void
    {
        $user = $this->demoActor();
        $groupA = $this->kartingCrew();
        $groupB = Group::where('name', 'Weekend Racers')->firstOrFail();

        $inviteB = $this->createInvite($groupB)['invite'];

        // Admin of group A is only a member of group B → 403.
        $this->actingAs($user)
            ->delete(route('groups.invites.destroy', [$groupB, $inviteB]))
            ->assertForbidden();

        // A manager of group B cannot revoke a group A invite through group B's route → 404.
        $inviteA = $this->createInvite($groupA)['invite'];
        $managerB = User::findOrFail(5);

        $this->actingAs($managerB)
            ->delete(route('groups.invites.destroy', [$groupB, $inviteA]))
            ->assertNotFound();

        $this->assertTrue($inviteA->fresh()->isPending());
    }

    public function test_tokens_are_random_and_only_hashes_are_persisted(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $first = $this->createInvite($group);
        $second = $this->createInvite($group);

        $this->assertSame(64, strlen($first['token']));
        $this->assertNotSame($first['token'], $second['token']);
        $this->assertSame(hash('sha256', $first['token']), $first['invite']->token_hash);
        $this->assertNotSame($first['token'], $first['invite']->token_hash);
        $this->assertFalse(Schema::hasColumn('invites', 'token'));
        $this->assertDatabaseMissing('invites', ['token_hash' => $first['token']]);
    }

    public function test_invitation_expiry_is_fourteen_days(): void
    {
        $this->demoActor();
        $group = $this->kartingCrew();

        $invite = $this->createInvite($group)['invite'];

        $this->assertSame(14, InviteService::EXPIRY_DAYS);
        $this->assertEqualsWithDelta(
            14 * 86400,
            $invite->created_at->diffInSeconds($invite->expires_at),
            2
        );
    }

    public function test_invalid_token_cannot_be_accepted(): void
    {
        $user = $this->demoActor();

        $this->actingAs($user)
            ->post(route('invites.accept', 'not-a-real-token'))
            ->assertNotFound();
    }

    public function test_invalid_token_show_returns_not_found(): void
    {
        $this->get(route('invites.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $this->demoActor();
        [$user, $driver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $result = $this->createInvite($group);
        $result['invite']->forceFill(['expires_at' => now()->subDay()])->save();

        $this->actingAs($user)
            ->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('invites.show', $result['token']));

        $this->assertFalse($group->members()->where('driver_id', $driver->id)->exists());
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $this->demoActor();
        [$user, $driver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $result = $this->createInvite($group);
        $this->invites()->revoke($result['invite']);

        $this->actingAs($user)
            ->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('invites.show', $result['token']));

        $this->assertFalse($group->members()->where('driver_id', $driver->id)->exists());
    }

    public function test_revocation_via_http_prevents_acceptance(): void
    {
        $admin = $this->demoActor();
        [$user, $driver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $result = $this->createInvite($group, null, $admin->driver);

        $this->actingAs($admin)
            ->delete(route('groups.invites.destroy', [$group, $result['invite']]))
            ->assertRedirect(route('groups.members', $group));

        $this->assertSame(InviteStatus::Revoked, $result['invite']->fresh()->status);

        $this->actingAs($user)
            ->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('invites.show', $result['token']));

        $this->assertFalse($group->members()->where('driver_id', $driver->id)->exists());
    }

    public function test_acceptance_requires_authentication(): void
    {
        $this->seed();
        $group = $this->kartingCrew();
        $result = $this->createInvite($group);

        $this->post(route('invites.accept', $result['token']))->assertRedirect(route('login'));
    }

    public function test_acceptance_creates_member_membership_only(): void
    {
        $this->demoActor();
        [$user, $driver] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $result = $this->createInvite($group);

        $this->actingAs($user)
            ->post(route('invites.accept', $result['token']), [
                'role' => GroupRole::Admin->value,
                'group_id' => 999,
                'driver_id' => 999,
            ])
            ->assertRedirect(route('groups.show', $group))
            ->assertSessionHas('status');

        $pivot = $group->members()->where('driver_id', $driver->id)->firstOrFail()->pivot;

        $this->assertSame(GroupRole::Member->value, $pivot->role);
        $this->assertSame(DriverAvailability::Available->value, $pivot->availability);

        $fresh = $result['invite']->fresh();
        $this->assertSame(InviteStatus::Accepted, $fresh->status);
        $this->assertNotNull($fresh->accepted_at);
    }

    public function test_another_account_cannot_reuse_an_accepted_invitation(): void
    {
        $this->demoActor();
        [$userA] = $this->userWithDriver();
        [$userB, $driverB] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $result = $this->createInvite($group);

        $this->actingAs($userA)
            ->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('groups.show', $group));

        $this->actingAs($userB)
            ->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('invites.show', $result['token']));

        $this->assertFalse($group->members()->where('driver_id', $driverB->id)->exists());
    }

    public function test_repeated_acceptance_is_idempotent(): void
    {
        $this->demoActor();
        [$user, $driver] = $this->userWithDriver();
        $group = $this->kartingCrew();
        $result = $this->createInvite($group);

        $this->actingAs($user)->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('groups.show', $group));
        $this->actingAs($user)->post(route('invites.accept', $result['token']))
            ->assertRedirect(route('groups.show', $group));

        $this->assertSame(
            1,
            DB::table('group_members')->where('group_id', $group->id)->where('driver_id', $driver->id)->count()
        );
    }

    public function test_concurrent_acceptance_does_not_duplicate_membership(): void
    {
        $this->demoActor();
        [$userA, $driverA] = $this->userWithDriver();
        [$userB, $driverB] = $this->userWithDriver();
        $group = $this->kartingCrew();
        $result = $this->createInvite($group);

        $first = $this->invites()->accept($result['token'], $userA);
        $second = $this->invites()->accept($result['token'], $userB);

        $this->assertSame('accepted', $first['status']);
        $this->assertSame('used', $second['status']);

        $this->assertSame(
            1,
            DB::table('group_members')
                ->where('group_id', $group->id)
                ->whereIn('driver_id', [$driverA->id, $driverB->id])
                ->count()
        );
    }

    public function test_invalid_email_input_is_rejected(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('groups.invites.store', $group), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Invite::count());
    }

    public function test_invite_acceptance_is_rate_limited(): void
    {
        $this->demoActor();
        [$user] = $this->userWithDriver();
        $group = $this->kartingCrew();
        $result = $this->createInvite($group);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post(route('invites.accept', $result['token']));
        }

        $this->actingAs($user)
            ->post(route('invites.accept', $result['token']))
            ->assertStatus(429);
    }

    public function test_group_invitation_mailable_contains_link_expiry_and_group(): void
    {
        $group = Group::factory()->create(['name' => 'Invite Test Crew']);
        $expires = now()->addDays(14);

        $mail = new GroupInvitation($group, 'https://example.test/invites/abc123', $expires);

        $mail->assertHasSubject('You are invited to join Invite Test Crew');
        $mail->assertSeeInHtml('Invite Test Crew');
        $mail->assertSeeInHtml('https://example.test/invites/abc123');
        $mail->assertSeeInHtml($expires->format('d M Y'));
    }

    public function test_link_only_invitation_sends_no_email_and_flashes_link(): void
    {
        Mail::fake();
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->actingAs($user)
            ->post(route('groups.invites.store', $group))
            ->assertRedirect(route('groups.members', $group));

        Mail::assertNothingSent();

        $invite = Invite::latest('id')->firstOrFail();
        $this->assertNull($invite->email);

        $encrypted = session('invite_url');
        $this->assertNotNull($encrypted);

        $url = Crypt::decryptString($encrypted);
        $this->assertStringContainsString('/invites/', $url);

        $token = Str::afterLast($url, '/');
        $this->assertSame(hash('sha256', $token), $invite->token_hash);
    }

    public function test_guest_can_view_invitation_landing_page(): void
    {
        $this->seed();
        $group = $this->kartingCrew();
        $result = $this->createInvite($group);

        $this->get(route('invites.show', $result['token']))
            ->assertOk()
            ->assertSee($group->name)
            ->assertSee('Sign in');
    }

    public function test_invitation_page_shows_expired_and_revoked_states(): void
    {
        $this->seed();
        $group = $this->kartingCrew();

        $expired = $this->createInvite($group);
        $expired['invite']->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get(route('invites.show', $expired['token']))
            ->assertOk()
            ->assertSee('expired');

        $revoked = $this->createInvite($group);
        $this->invites()->revoke($revoked['invite']);

        $this->get(route('invites.show', $revoked['token']))
            ->assertOk()
            ->assertSee('revoked');
    }

    public function test_registration_returns_to_the_intended_invitation(): void
    {
        $this->seed();
        $group = $this->kartingCrew();
        $result = $this->createInvite($group);

        $this->get(route('invites.show', $result['token']))->assertOk();

        $this->post('/register', [
            'name' => 'New Joiner',
            'email' => 'joiner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('invites.show', $result['token']));
    }

    public function test_members_page_lists_pending_invitations_for_organizers(): void
    {
        $user = $this->demoActor();
        $group = $this->kartingCrew();

        $this->createInvite($group, 'pending@example.com', $user->driver);

        $this->actingAs($user)
            ->get(route('groups.members', $group))
            ->assertOk()
            ->assertSee('Pending invitations')
            ->assertSee('pending@example.com');
    }

    public function test_regenerate_rotates_the_token_and_retires_the_old_one(): void
    {
        $admin = $this->demoActor();
        [$user] = $this->userWithDriver();
        $group = $this->kartingCrew();

        $old = $this->createInvite($group, null, $admin->driver);

        $this->actingAs($admin)
            ->post(route('groups.invites.regenerate', [$group, $old['invite']]))
            ->assertRedirect(route('groups.members', $group))
            ->assertSessionHas('invite_url');

        $this->assertSame(InviteStatus::Revoked, $old['invite']->fresh()->status);

        $new = Invite::where('group_id', $group->id)
            ->where('status', InviteStatus::Pending->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($old['invite']->id, $new->id);

        $this->actingAs($user)
            ->post(route('invites.accept', $old['token']))
            ->assertRedirect(route('invites.show', $old['token']));
    }
}
