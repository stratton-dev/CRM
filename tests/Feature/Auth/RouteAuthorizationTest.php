<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use App\Http\Middleware\SupabaseAuthenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_without_clients_view_permission(): void
    {
        $this->withoutMiddleware(SupabaseAuthenticate::class);

        Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '9990001112',
            'address_line1' => 'Main 9',
            'postal_code' => '00-009',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/clients');

        $response->assertStatus(403);
    }
}
