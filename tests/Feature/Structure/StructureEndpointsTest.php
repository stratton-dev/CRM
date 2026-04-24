<?php

namespace Tests\Feature\Structure;

use App\Http\Middleware\SupabaseAuthenticate;
use App\Models\User;
use App\Services\Structure\HierarchicalCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_users_idempotently_with_the_same_idempotency_key(): void
    {
        $this->withoutMiddleware(SupabaseAuthenticate::class);

        $actor = User::factory()->create([
            'keycloak_id' => 'actor-1',
            'role_cached' => 'ADMIN',
            'team_group_path' => '/teams/warszawa01',
        ]);

        $this->actingAs($actor);

        $payload = [
            'keycloak_id' => 'user-1',
            'name' => 'Jan Kowalski',
            'email' => 'jan.kowalski@example.test',
            'role' => 'DIRECTOR',
            'team_group_path' => '/teams/warszawa01',
        ];

        $first = $this->postJson('/api/v1/users', $payload, ['Idempotency-Key' => 'req-1']);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/users', $payload, ['Idempotency-Key' => 'req-1']);
        $second->assertStatus(200);

        $this->assertSame($first->json('hierarchicalCode'), $second->json('hierarchicalCode'));
        $this->assertSame(1, User::query()->where('keycloak_id', 'user-1')->count());
    }

    public function test_increments_hierarchical_code_counters_within_a_scope(): void
    {
        $service = app(HierarchicalCodeService::class);

        $first = $service->generate('/teams/warszawa01', null, 'JK');
        $second = $service->generate('/teams/warszawa01', null, 'JK');

        $this->assertSame('WAR001/JK001', $first);
        $this->assertSame('WAR002/JK001', $second);
    }

    public function test_prevents_moving_a_user_under_its_descendant(): void
    {
        $this->withoutMiddleware(SupabaseAuthenticate::class);

        $actor = User::factory()->create([
            'keycloak_id' => 'actor-2',
            'role_cached' => 'ADMIN',
            'team_group_path' => '/teams/warszawa01',
        ]);

        $root = User::factory()->create([
            'keycloak_id' => 'root-1',
            'role_cached' => 'DIRECTOR',
            'team_group_path' => '/teams/warszawa01',
            'parent_keycloak_id' => null,
            'hierarchical_code' => 'WAR001/AA001',
        ]);

        $child = User::factory()->create([
            'keycloak_id' => 'child-1',
            'role_cached' => 'MANAGER',
            'team_group_path' => '/teams/warszawa01',
            'parent_keycloak_id' => 'root-1',
            'hierarchical_code' => 'WAR001/AA001/BB001',
        ]);

        User::factory()->create([
            'keycloak_id' => 'grandchild-1',
            'role_cached' => 'SALES',
            'team_group_path' => '/teams/warszawa01',
            'parent_keycloak_id' => 'child-1',
            'hierarchical_code' => 'WAR001/AA001/BB001/CC001',
        ]);

        $this->actingAs($actor);

        $response = $this->postJson('/api/v1/structure/move', [
            'user_keycloak_id' => $root->keycloak_id,
            'new_parent_keycloak_id' => 'grandchild-1',
        ]);

        $response->assertStatus(422);
    }
}
