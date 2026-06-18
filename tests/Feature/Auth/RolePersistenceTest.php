<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SupabaseTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: an admin changes a user's role in the Users/Structure tab (DB is
 * updated). The user still holds an already-issued JWT whose app_metadata.role
 * carries the OLD role. The auth middleware must NOT overwrite the DB role from
 * that stale token on the next request — otherwise the admin's change silently
 * reverts ("nie zapisało"). DB is the source of truth for roles.
 */
class RolePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeTokenWithRole(string $supabaseId, string $email, string $appMetadataRole): void
    {
        $fake = new class($supabaseId, $email, $appMetadataRole) extends SupabaseTokenService {
            public function __construct(
                private string $sub,
                private string $mail,
                private string $role,
            ) {}

            public function decode(string $token): array
            {
                return [
                    'sub' => $this->sub,
                    'email' => $this->mail,
                    'iss' => 'test',
                    'app_metadata' => ['role' => $this->role],
                    'user_metadata' => ['name' => 'Maciej Hagno'],
                ];
            }
        };

        $this->app->instance(SupabaseTokenService::class, $fake);
    }

    public function test_existing_db_role_is_not_reverted_by_stale_token_role(): void
    {
        $director = Role::firstOrCreate(['code' => 'DIRECTOR'], ['name' => 'DIRECTOR']);
        Role::firstOrCreate(['code' => 'ADMIN'], ['name' => 'ADMIN']);

        // Admin already promoted this user ADMIN -> DIRECTOR (persisted in DB).
        $user = User::factory()->create([
            'supabase_id' => '11111111-1111-1111-1111-111111111111',
            'email' => 'm.hagno@stratton-prime.pl',
            'role_id' => $director->id,
            'role_cached' => 'DIRECTOR',
        ]);

        // The user's old JWT still says ADMIN.
        $this->fakeTokenWithRole($user->supabase_id, $user->email, 'ADMIN');

        $this->withHeaders(['Authorization' => 'Bearer stale-token'])
            ->getJson('/api/v1/me')
            ->assertOk();

        $user->refresh();
        $this->assertSame('DIRECTOR', $user->role_cached, 'Stale token role must NOT overwrite the DB role.');
        $this->assertSame($director->id, $user->role_id, 'Stale token must NOT overwrite role_id either.');
    }

    public function test_token_role_still_initializes_a_user_without_a_role(): void
    {
        Role::firstOrCreate(['code' => 'SALES'], ['name' => 'SALES']);

        // Fresh user with no role yet (e.g. first login of a Supabase-provisioned account).
        $user = User::factory()->create([
            'supabase_id' => '22222222-2222-2222-2222-222222222222',
            'email' => 'fresh@stratton-prime.pl',
            'role_id' => null,
            'role_cached' => null,
        ]);

        $this->fakeTokenWithRole($user->supabase_id, $user->email, 'SALES');

        $this->withHeaders(['Authorization' => 'Bearer fresh-token'])
            ->getJson('/api/v1/me')
            ->assertOk();

        $user->refresh();
        $this->assertSame('SALES', $user->role_cached, 'Token role must initialize a user that has no DB role.');
    }
}
