<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use App\Http\Middleware\SupabaseAuthenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_without_meetings_create_permission(): void
    {
        $this->withoutMiddleware(SupabaseAuthenticate::class);

        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '7778889990',
            'address_line1' => 'Main 8',
            'postal_code' => '00-008',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/meetings', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'valid_until' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(403);
    }
}
