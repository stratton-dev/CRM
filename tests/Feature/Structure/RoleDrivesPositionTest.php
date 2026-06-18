<?php

namespace Tests\Feature\Structure;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SupabaseTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opcja A: changing a user's role through the Users tab repositions them in the
 * structure tree in the same request (DB position follows the new role).
 */
class RoleDrivesPositionTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(string $adminSupabaseId): void
    {
        $fake = new class($adminSupabaseId) extends SupabaseTokenService {
            public function __construct(private string $sub) {}

            public function decode(string $token): array
            {
                return [
                    'sub' => $this->sub,
                    'email' => 'admin@stratton-prime.pl',
                    'iss' => 'test',
                    'app_metadata' => ['role' => 'ADMIN'],
                ];
            }
        };
        $this->app->instance(SupabaseTokenService::class, $fake);
    }

    private function seedRoles(): void
    {
        foreach (['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'] as $code) {
            Role::firstOrCreate(['code' => $code], ['name' => $code]);
        }
    }

    public function test_changing_role_to_manager_with_parent_moves_node_under_director(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create([
            'supabase_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'role_cached' => 'ADMIN',
            'role_id' => Role::where('code', 'ADMIN')->value('id'),
        ]);
        $director = User::factory()->create([
            'supabase_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'role_cached' => 'DIRECTOR',
            'role_id' => Role::where('code', 'DIRECTOR')->value('id'),
            'parent_supabase_id' => null,
        ]);
        $target = User::factory()->create([
            'supabase_id' => 'tttttttt-tttt-tttt-tttt-tttttttttttt',
            'role_cached' => 'SALES',
            'role_id' => Role::where('code', 'SALES')->value('id'),
            'parent_supabase_id' => null,
        ]);

        $this->actAsAdmin($admin->supabase_id);

        $this->withHeaders(['Authorization' => 'Bearer x'])
            ->patchJson("/api/v1/users/{$target->supabase_id}", [
                'role' => 'MANAGER',
                'parent_supabase_id' => $director->supabase_id,
            ])
            ->assertOk()
            ->assertJsonFragment(['role' => 'MANAGER']);

        $target->refresh();
        $this->assertSame('MANAGER', $target->role_cached);
        $this->assertSame($director->supabase_id, $target->parent_supabase_id);
    }

    public function test_promoting_to_director_detaches_to_root(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create([
            'supabase_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'role_cached' => 'ADMIN',
            'role_id' => Role::where('code', 'ADMIN')->value('id'),
        ]);
        $director = User::factory()->create([
            'supabase_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'role_cached' => 'DIRECTOR',
            'role_id' => Role::where('code', 'DIRECTOR')->value('id'),
        ]);
        $manager = User::factory()->create([
            'supabase_id' => 'mmmmmmmm-mmmm-mmmm-mmmm-mmmmmmmmmmmm',
            'role_cached' => 'MANAGER',
            'role_id' => Role::where('code', 'MANAGER')->value('id'),
            'parent_supabase_id' => $director->supabase_id,
        ]);

        $this->actAsAdmin($admin->supabase_id);

        $this->withHeaders(['Authorization' => 'Bearer x'])
            ->patchJson("/api/v1/users/{$manager->supabase_id}", [
                'role' => 'DIRECTOR',
                'parent_supabase_id' => null,
            ])
            ->assertOk()
            ->assertJsonFragment(['role' => 'DIRECTOR']);

        $manager->refresh();
        $this->assertSame('DIRECTOR', $manager->role_cached);
        $this->assertNull($manager->parent_supabase_id);
    }
}
