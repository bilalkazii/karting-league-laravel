<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
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
        $this->post('/register', [
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
        $this->post('/register', [
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
}
