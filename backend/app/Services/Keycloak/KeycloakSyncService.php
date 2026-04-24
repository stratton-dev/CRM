<?php

namespace App\Services\Keycloak;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class KeycloakSyncService
{
    private const ROLE_PRIORITY = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'];

    public function __construct(
        private readonly KeycloakAdminClient $client,
        private readonly KeycloakTeamService $teams
    )
    {
    }

    public function syncAllUsers(bool $allowRestore = false): SyncReport
    {
        if (!config('keycloak.sync_enabled')) {
            return SyncReport::disabled();
        }

        $clientId = (string) config('keycloak.client_id');
        if ($clientId === '') {
            throw new RuntimeException('Missing Keycloak client_id for sync.');
        }

        $clientUuid = $this->client->findClientUuidByClientId($clientId);
        $report = new SyncReport('ok');
        $teamMembers = [];
        try {
            $this->teams->refreshTeams();
            $teamMembers = $this->buildTeamMembersMap();
        } catch (\Throwable $exception) {
            $report->messages[] = 'Team sync failed: '.$exception->getMessage();
        }

        $pageSize = (int) config('keycloak.admin_page_size', 100);
        $maxUsers = (int) config('keycloak.admin_max_users', 5000);
        $first = 0;
        $processed = 0;
        $seenIds = [];
        $restoredCount = 0;

        while (true) {
            $users = $this->client->listUsers($first, $pageSize);
            if (!$users) {
                break;
            }

            foreach ($users as $kcUser) {
                if ($processed >= $maxUsers) {
                    $report->messages[] = 'Max users limit reached.';
                    $report->status = 'partial';
                    break 2;
                }

                try {
                    $kcId = (string) ($kcUser['id'] ?? '');
                    if ($kcId !== '') {
                        $seenIds[$kcId] = true;
                    }
                    $result = $this->syncUserPayload($kcUser, $clientUuid, $teamMembers, $allowRestore);
                    $report->{$result['status']}++;
                    if ($allowRestore && $result['restored']) {
                        $restoredCount++;
                    }
                } catch (\Throwable $exception) {
                    $report->errors++;
                    $report->messages[] = $exception->getMessage();
                }

                $processed++;
            }

            if (count($users) < $pageSize) {
                break;
            }

            $first += $pageSize;
        }

        if ($report->status === 'ok' && $report->errors === 0) {
            $missingIds = array_keys($seenIds);
            $removedCount = User::query()
                ->whereNotNull('keycloak_id')
                ->when($missingIds !== [], fn ($q) => $q->whereNotIn('keycloak_id', $missingIds))
                ->update([
                    'is_removed_from_structure' => true,
                    'enabled' => false,
                    'sync_error' => 'missing_in_keycloak',
                    'last_synced_at' => Carbon::now(),
                ]);

            if ($removedCount > 0) {
                $report->skipped += $removedCount;
                $report->messages[] = "Marked {$removedCount} users removed (missing in Keycloak).";
            }
        }

        if ($allowRestore && $restoredCount > 0) {
            $report->messages[] = "Restored {$restoredCount} users present in Keycloak.";
        }

        return $report;
    }

    public function syncUserById(string $keycloakId): void
    {
        $payload = $this->client->getUser($keycloakId);
        if (!$payload) {
            throw new RuntimeException('Keycloak user not found.');
        }

        $clientId = (string) config('keycloak.client_id');
        if ($clientId === '') {
            throw new RuntimeException('Missing Keycloak client_id for sync.');
        }

        $clientUuid = $this->client->findClientUuidByClientId($clientId);
        $this->syncUserPayload($payload, $clientUuid, $this->buildTeamMembersMap());
    }

    public function extractTeamGroupPath(array $groups): ?string
    {
        foreach ($groups as $group) {
            $path = $group['path'] ?? null;
            if (!is_string($path)) {
                continue;
            }
            if (str_starts_with($path, '/teams/')) {
                return $path;
            }
        }

        return null;
    }

    public function withLock(callable $callback): SyncReport
    {
        $lock = Cache::lock('kc-sync', 60);
        if (!$lock->get()) {
            return SyncReport::locked();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function syncUserPayload(array $kcUser, string $clientUuid, array $teamMembers = [], bool $allowRestore = false): array
    {
        $keycloakId = (string) ($kcUser['id'] ?? '');
        if ($keycloakId === '') {
            throw new RuntimeException('Missing Keycloak user id.');
        }

        $email = $kcUser['email'] ?? null;
        $firstName = $kcUser['firstName'] ?? null;
        $lastName = $kcUser['lastName'] ?? null;
        $username = $kcUser['username'] ?? null;
        $enabled = (bool) ($kcUser['enabled'] ?? true);

        $name = trim((string) ($firstName.' '.$lastName));
        if ($name === '') {
            $name = (string) ($username ?: $email ?: $keycloakId);
        }

        $teamGroupPath = $teamMembers[$keycloakId] ?? null;
        if (!$teamGroupPath && $teamMembers === []) {
            $groups = $this->client->getUserGroups($keycloakId);
            $teamGroupPath = $this->extractTeamGroupPath($groups);
        }

        $roles = $this->client->getUserClientRoles($keycloakId, $clientUuid);
        $roleCached = $this->selectRole($roles);

        $now = Carbon::now();

        $attributes = [
            'email' => $email ?: null,
            'name' => $name,
            'enabled' => $enabled,
            'team_group_path' => $teamGroupPath,
            'role_cached' => $roleCached,
            'last_synced_at' => $now,
            'sync_error' => null,
        ];

        $existing = User::query()->where('keycloak_id', $keycloakId)->first();
        if ($existing) {
            $restored = false;
            if ($allowRestore && $existing->is_removed_from_structure) {
                $attributes['is_removed_from_structure'] = false;
                $restored = true;
            }
            $existing->fill($attributes)->save();
            return ['status' => 'updated', 'restored' => $restored];
        }

        if ($email) {
            $existingByEmail = User::query()->where('email', $email)->first();
            if ($existingByEmail) {
                $attributes['keycloak_id'] = $keycloakId;
                $restored = false;
                if ($allowRestore && $existingByEmail->is_removed_from_structure) {
                    $attributes['is_removed_from_structure'] = false;
                    $restored = true;
                }
                $existingByEmail->fill($attributes)->save();
                return ['status' => 'updated', 'restored' => $restored];
            }
        }

        User::create(array_merge($attributes, [
            'keycloak_id' => $keycloakId,
            'password' => Str::random(32),
            'active' => true,
            'pending_setup' => true,
        ]));

        return ['status' => 'created', 'restored' => false];
    }

    private function selectRole(array $roles): ?string
    {
        $roleNames = collect($roles)
            ->map(fn ($role) => $role['name'] ?? null)
            ->filter()
            ->map(fn ($role) => (string) $role)
            ->values()
            ->all();

        $roleMap = config('keycloak.role_map', []);
        $mapped = array_map(function (string $role) use ($roleMap): string {
            $mappedRole = $roleMap[Str::lower($role)] ?? $role;
            return Str::upper($mappedRole);
        }, $roleNames);

        foreach (self::ROLE_PRIORITY as $role) {
            if (in_array($role, $mapped, true)) {
                return $role;
            }
        }

        return null;
    }

    private function buildTeamMembersMap(): array
    {
        $map = [];
        $groups = $this->teams->listTeamGroups(true);
        foreach ($groups as $group) {
            $groupId = $group['id'] ?? null;
            $path = $group['path'] ?? null;
            if (!is_string($groupId) || $groupId === '' || !is_string($path) || $path === '') {
                continue;
            }

            $first = 0;
            $pageSize = (int) config('keycloak.admin_page_size', 100);
            while (true) {
                $members = $this->client->listGroupMembers($groupId, $first, $pageSize);
                if (!$members) {
                    break;
                }

                foreach ($members as $member) {
                    $userId = $member['id'] ?? null;
                    if (is_string($userId) && $userId !== '' && !isset($map[$userId])) {
                        $map[$userId] = $path;
                    }
                }

                if (count($members) < $pageSize) {
                    break;
                }

                $first += $pageSize;
            }
        }

        return $map;
    }
}
