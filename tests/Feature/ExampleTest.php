<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** The main domain is the platform's address: visitors land on the Super Admin sign-in. */
    public function test_the_main_domain_opens_the_super_admin_sign_in(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('fi-login', false);
        $this->get('/shop')->assertRedirect('/');
    }

    /** The form at /login signs a Super Admin in, through the same request flow as a browser. */
    public function test_a_super_admin_signs_in_at_login(): void
    {
        User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => bcrypt('secret-pass'), 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $page = $this->get('/login')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $page, $snapshot);
        $this->assertNotEmpty($snapshot, 'login form not rendered');

        $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson(\Livewire\Mechanisms\HandleRequests\EndpointResolver::updatePath(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => html_entity_decode($snapshot[1]),
                'updates' => ['data.email' => 'owner@example.test', 'data.password' => 'secret-pass'],
                'calls' => [['path' => '', 'method' => 'authenticate', 'params' => []]],
            ]],
        ]);

        $response->assertOk();
        $this->assertAuthenticated('admin');
    }
}
