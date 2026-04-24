<?php

namespace Tests\Feature;

use App\Http\Middleware\KeycloakAuthenticate;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdminClient;
use App\Services\Keycloak\KeycloakSyncService;
use App\Services\Keycloak\SyncReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class KeycloakSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_endpoint_requires_admin(): void
    {
        $this->withoutMiddleware(KeycloakAuthenticate::class);

        $user = User::factory()->create(['role_cached' => 'MANAGER']);
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/admin/keycloak/sync');
        $response->assertStatus(403);
    }

    public function test_sync_does_not_overwrite_structure_fields(): void
    {
        $existing = User::create([
            'keycloak_id' => 'kc-1',
            'email' => 'old@example.test',
            'name' => 'Old Name',
            'role_cached' => 'DIRECTOR',
            'team_group_path' => '/teams/old',
            'parent_keycloak_id' => 'parent-1',
            'hierarchical_code' => 'OLD-001',
            'password' => 'secret',
            'active' => true,
        ]);

        $service = new KeycloakSyncService(new class extends KeycloakAdminClient {
            public function __construct()
            {
            }

            public function listUsers(int $first = 0, ?int $max = null): array
            {
                return [[
                    'id' => 'kc-1',
                    'email' => 'new@example.test',
                    'firstName' => 'New',
                    'lastName' => 'Name',
                    'username' => 'newname',
                    'enabled' => true,
                ]];
            }

            public function getUserGroups(string $userId): array
            {
                return [['path' => '/teams/nowa']];
            }

            public function getUserClientRoles(string $userId, string $clientUuid): array
            {
                return [['name' => 'manager']];
            }

            public function findClientUuidByClientId(string $clientId): string
            {
                return 'client-uuid';
            }
        });

        config()->set('keycloak.sync_enabled', true);
        config()->set('keycloak.client_id', 'crm-client');

        $service->syncAllUsers();

        $existing->refresh();
        $this->assertSame('parent-1', $existing->parent_keycloak_id);
        $this->assertSame('OLD-001', $existing->hierarchical_code);
        $this->assertSame('new@example.test', $existing->email);
        $this->assertSame('New Name', $existing->name);
        $this->assertSame('/teams/nowa', $existing->team_group_path);
        $this->assertSame('MANAGER', $existing->role_cached);
    }

    public function test_extract_team_group_path_returns_first_team(): void
    {
        $service = new KeycloakSyncService(new class extends KeycloakAdminClient {
            public function __construct()
            {
            }
        });

        $path = $service->extractTeamGroupPath([
            ['path' => '/other'],
            ['path' => '/teams/warszawa01'],
            ['path' => '/teams/warszawa02'],
        ]);

        $this->assertSame('/teams/warszawa01', $path);
    }

    public function test_lock_prevents_parallel_sync(): void
    {
        $service = new KeycloakSyncService(new class extends KeycloakAdminClient {
            public function __construct()
            {
            }
        });

        $lock = Cache::lock('kc-sync', 60);
        $lock->get();

        $report = $service->withLock(fn () => SyncReport::disabled());
        $this->assertSame('locked', $report->status);

        $lock->release();
    }
}
