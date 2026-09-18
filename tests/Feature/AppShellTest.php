<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    private function demoActor(): User
    {
        $this->seed();

        return User::where('email', 'demo@karting.app')->firstOrFail();
    }

    public function test_dashboard_renders_with_demo_driver(): void
    {
        $this->actingAs($this->demoActor())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Bilal Darji')
            ->assertSee('Karting Crew');
    }

    public function test_nav_routes_render_for_authenticated_user(): void
    {
        $this->actingAs($this->demoActor());

        foreach (['/groups', '/races', '/championship', '/chat', '/notifications', '/settings', '/race-setup'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_driver_profile_route_redirects_for_self(): void
    {
        $response = $this->actingAs($this->demoActor())
            ->get('/profile');

        $response->assertRedirect();
    }
}