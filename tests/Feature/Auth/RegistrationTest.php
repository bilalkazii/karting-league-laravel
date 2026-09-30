<?php

namespace Tests\Feature\Auth;

use App\Models\Group;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function inviteToken(): string
    {
        $this->seed();
        $group = Group::where('name', 'Karting Crew')->firstOrFail();

        return app(InviteService::class)->create($group, null, null)['token'];
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_requires_a_valid_invitation(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('invite');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_new_users_can_register_with_an_invitation(): void
    {
        $token = $this->inviteToken();

        $response = $this->post('/register', [
            'invite' => $token,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_provisions_profile_and_driver(): void
    {
        $token = $this->inviteToken();

        $this->post('/register', [
            'invite' => $token,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->profile);
        $this->assertEquals('Test User', $user->profile->full_name);
        $this->assertNotNull($user->driver);
        $this->assertEquals('TE', $user->driver->nickname);
        $this->assertEquals(1200, $user->driver->rating);
    }

    public function test_registration_is_idempotent_for_existing_identity(): void
    {
        $token = $this->inviteToken();

        $this->post('/register', [
            'invite' => $token,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();

        event(new Registered($user));

        $this->assertEquals(1, $user->profile()->count());
        $this->assertEquals(1, $user->driver()->count());
    }

    public function test_registration_rejects_email_that_does_not_match_the_invitation(): void
    {
        $this->seed();
        $group = Group::where('name', 'Karting Crew')->firstOrFail();
        $token = app(InviteService::class)->create($group, null, 'invited@example.com')['token'];

        $this->post('/register', [
            'invite' => $token,
            'name' => 'Imposter',
            'email' => 'someone-else@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'someone-else@example.com']);
    }
}
