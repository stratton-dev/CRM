<?php

namespace App\Services\Keycloak;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use App\Models\User;

class KeycloakProvisioningService
{
    public function __construct(
        private readonly KeycloakAdminClient $client,
        private readonly KeycloakTeamService $teams
    ) {
    }

    public function provisionUser(array $payload, string $teamPath, string $role): array
    {
        if (!config('keycloak.sync_enabled')) {
            throw new RuntimeException('Keycloak provisioning is disabled.');
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Missing email for Keycloak user creation.');
        }

        $name = trim((string) ($payload['name'] ?? ''));
        [$firstName, $lastName] = $this->splitName($name);

        $userPayload = [
            'username' => $email,
            'email' => $email,
            'enabled' => true,
            'firstName' => $firstName ?: null,
            'lastName' => $lastName ?: null,
            'attributes' => [
                'locale' => ['pl'],
            ],
        ];

        $created = $this->client->createUser($userPayload);
        $userId = (string) ($created['id'] ?? '');
        if ($userId === '') {
            throw new RuntimeException('Failed to create user in Keycloak.');
        }

        if ($teamPath !== '') {
            $this->teams->moveUserToTeam($userId, $teamPath);
        }

        $clientId = (string) config('keycloak.client_id');
        if ($clientId !== '') {
            $clientUuid = $this->client->findClientUuidByClientId($clientId);
            $roleName = $this->resolveKeycloakRoleName($role);
            if ($roleName !== '') {
                try {
                    $roleRep = $this->client->findClientRoleByName($clientUuid, $roleName);
                    if ($roleRep) {
                        $this->client->assignClientRolesToUser($userId, $clientUuid, [$roleRep]);
                    } else {
                        Log::channel('keycloak')->warning('Keycloak client role not found', [
                            'user_id' => $userId,
                            'role' => $roleName,
                        ]);
                    }
                } catch (\Throwable $exception) {
                    Log::channel('keycloak')->warning('Keycloak client role assignment skipped', [
                        'user_id' => $userId,
                        'role' => $roleName,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $inviteSent = null;
        $inviteError = null;
        $actions = $this->inviteActions();
        if ($actions) {
            $lifespan = (int) config('keycloak.invite_lifespan', 0);
            try {
                $this->client->executeActionsEmail($userId, $actions, $lifespan > 0 ? $lifespan : null);
                $inviteSent = true;
            } catch (\Throwable $exception) {
                $inviteSent = false;
                $inviteError = $exception->getMessage();
                Log::channel('keycloak')->warning('Keycloak invite email failed', [
                    'user_id' => $userId,
                    'error' => $inviteError,
                ]);
            }
        }

        return [
            'id' => $userId,
            'invite_sent' => $inviteSent,
            'invite_error' => $inviteError,
        ];
    }

    public function restoreUser(User $user, string $teamPath, string $role): array
    {
        if (!config('keycloak.sync_enabled')) {
            throw new RuntimeException('Keycloak provisioning is disabled.');
        }

        $existing = null;
        $kcId = (string) ($user->keycloak_id ?? '');
        if ($kcId !== '') {
            try {
                $existing = $this->client->getUser($kcId);
            } catch (\Throwable $exception) {
                Log::channel('keycloak')->warning('Keycloak user lookup failed during restore', [
                    'user_id' => $kcId,
                    'error' => $exception->getMessage(),
                ]);
                $existing = null;
            }
        }

        if ($existing) {
            $enabled = (bool) ($existing['enabled'] ?? true);
            if (!$enabled) {
                $this->client->updateUser($kcId, ['enabled' => true]);
            }

            if ($teamPath !== '') {
                $this->teams->moveUserToTeam($kcId, $teamPath);
            }

            $clientId = (string) config('keycloak.client_id');
            if ($clientId !== '') {
                $clientUuid = $this->client->findClientUuidByClientId($clientId);
                $roleName = $this->resolveKeycloakRoleName($role);
                if ($roleName !== '') {
                    $roleRep = $this->client->findClientRoleByName($clientUuid, $roleName);
                    if ($roleRep) {
                        $this->client->assignClientRolesToUser($kcId, $clientUuid, [$roleRep]);
                    } else {
                        Log::channel('keycloak')->warning('Keycloak client role not found during restore', [
                            'user_id' => $kcId,
                            'role' => $roleName,
                        ]);
                    }
                }
            }

            return [
                'id' => $kcId,
                'invite_sent' => null,
                'invite_error' => null,
                'restored' => true,
                'created' => false,
            ];
        }

        $payload = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        $created = $this->provisionUser($payload, $teamPath, $role);

        return [
            'id' => $created['id'] ?? '',
            'invite_sent' => $created['invite_sent'] ?? null,
            'invite_error' => $created['invite_error'] ?? null,
            'restored' => true,
            'created' => true,
        ];
    }

    public function deleteUser(User $user): void
    {
        if (!config('keycloak.sync_enabled')) {
            throw new RuntimeException('Keycloak provisioning is disabled.');
        }

        $kcId = (string) ($user->keycloak_id ?? '');
        if ($kcId === '') {
            return;
        }

        try {
            $this->client->deleteUser($kcId);
        } catch (\Throwable $exception) {
            Log::channel('keycloak')->warning('Keycloak user delete failed', [
                'user_id' => $kcId,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    private function inviteActions(): array
    {
        $raw = (string) config('keycloak.invite_actions', '');
        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $action) => strtoupper(trim($action)))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveKeycloakRoleName(string $role): string
    {
        $role = Str::upper($role);
        $roleMap = config('keycloak.role_map', []);
        foreach ($roleMap as $kcRole => $localRole) {
            if (Str::upper((string) $localRole) === $role) {
                return (string) $kcRole;
            }
        }

        return Str::lower($role);
    }

    private function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name);
        if (!$parts) {
            return ['', ''];
        }

        $first = array_shift($parts);
        $last = trim(implode(' ', $parts));

        return [$first ?: '', $last];
    }
}
