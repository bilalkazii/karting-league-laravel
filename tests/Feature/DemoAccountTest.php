<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_account_can_log_in(): void
    {
        $this->seed();

        $response = $this->post('/login', [
            'email' => 'demo@karting.app',
            'password' => 'karting-demo-2026',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_demo_account_has_full_identity_chain(): void
    {
        $this->seed();

        $demo = User::where('email', 'demo@karting.app')->firstOrFail();

        $this->assertNotNull($demo->profile);
        $this->assertNotNull($demo->driver);
        $this->assertEquals('BD', $demo->driver->nickname);
        $this->assertEquals('Karting Crew', $demo->driver->groups()->where('name', 'Karting Crew')->firstOrFail()->name);
    }

    public function test_guest_is_redirected_away_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
    }
}