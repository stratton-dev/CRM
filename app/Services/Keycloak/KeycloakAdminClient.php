<?php

namespace App\Services\Keycloak;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class KeycloakAdminClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function getAdminToken(): string
    {
        $cacheKey = 'keycloak.admin.token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $baseUrl = rtrim((string) config('keycloak.base_url'), '/');
        $realm = (string) config('keycloak.realm');
        $clientId = (string) config('keycloak.admin_client_id');
        $clientSecret = (string) config('keycloak.admin_client_secret');

        if (!$baseUrl || !$realm || !$clientId || !$clientSecret) {
            throw new RuntimeException('Keycloak admin client configuration is missing.');
        }

        $response = $this->http
            ->timeout((int) config('keycloak.admin_timeout', 10))
            ->retry(2, 200)
            ->asForm()
            ->post($baseUrl.'/realms/'.$realm.'/protocol/openid-connect/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();

        $payload = $response->json();
        $token = $payload['access_token'] ?? null;
        $expires = (int) ($payload['expires_in'] ?? 0);

        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Keycloak admin token is missing.');
        }

        Cache::put($cacheKey, $token, max(1, $expires - 30));

        return $token;
    }

    public function listUsers(int $first = 0, ?int $max = null): array
    {
        $max ??= (int) config('keycloak.admin_page_size', 100);
        $response = $this->adminRequest()
            ->get($this->adminUrl('/users'), [
                'first' => $first,
                'max' => $max,
            ]);

        return $this->decodeResponse($response);
    }

    public function getUser(string $userId): array
    {
        $response = $this->adminRequest()
            ->get($this->adminUrl('/users/'.$userId));

        return $this->decodeResponse($response);
    }

    public function getUserGroups(string $userId): array
    {
        $response = $this->adminRequest()
            ->get($this->adminUrl('/users/'.$userId.'/groups'));

        return $this->decodeResponse($response);
    }

    public function getUserClientRoles(string $userId, string $clientUuid): array
    {
        $response = $this->adminRequest()
            ->get($this->adminUrl('/users/'.$userId.'/role-mappings/clients/'.$clientUuid));

        return $this->decodeResponse($response);
    }

    public function listGroups(int $first = 0, ?int $max = null, ?string $search = null): array
    {
        $max ??= (int) config('keycloak.admin_page_size', 100);
        $params = [
            'first' => $first,
            'max' => $max,
            'briefRepresentation' => 'false',
        ];
        if ($search !== null && $search !== '') {
            $params['search'] = $search;
        }

        $response = $this->adminRequest()
            ->get($this->adminUrl('/groups'), $params);

        return $this->decodeResponse($response);
    }

    public function getGroup(string $groupId): array
    {
        $response = $this->adminRequest()
            ->get($this->adminUrl('/groups/'.$groupId), [
                'briefRepresentation' => 'false',
            ]);

        return $this->decodeResponse($response);
    }

    public function listAllGroups(?string $search = null): array
    {
        $all = [];
        $first = 0;
        $pageSize = (int) config('keycloak.admin_page_size', 100);

        while (true) {
            $chunk = $this->listGroups($first, $pageSize, $search);
            if (!$chunk) {
                break;
            }

            $all = array_merge($all, $chunk);

            if (count($chunk) < $pageSize) {
                break;
            }

            $first += $pageSize;
        }

        return $all;
    }

    public function listGroupMembers(string $groupId, int $first = 0, ?int $max = null): array
    {
        $max ??= (int) config('keycloak.admin_page_size', 100);
        $response = $this->adminRequest()
            ->get($this->adminUrl('/groups/'.$groupId.'/members'), [
                'first' => $first,
                'max' => $max,
            ]);

        return $this->decodeResponse($response);
    }

    public function addUserToGroup(string $userId, string $groupId): void
    {
        $response = $this->adminRequest()
            ->put($this->adminUrl('/users/'.$userId.'/groups/'.$groupId));

        if (!in_array($response->status(), [204, 201], true)) {
            $response->throw();
        }
    }

    public function removeUserFromGroup(string $userId, string $groupId): void
    {
        $response = $this->adminRequest()
            ->delete($this->adminUrl('/users/'.$userId.'/groups/'.$groupId));

        if (!in_array($response->status(), [204, 200], true)) {
            $response->throw();
        }
    }

    public function listGroupChildren(string $groupId, int $first = 0, ?int $max = null): array
    {
        $max ??= (int) config('keycloak.admin_page_size', 100);
        $response = $this->adminRequest()
            ->get($this->adminUrl('/groups/'.$groupId.'/children'), [
                'first' => $first,
                'max' => $max,
                'briefRepresentation' => 'false',
            ]);

        return $this->decodeResponse($response);
    }

    public function createGroup(string $name, ?string $displayName = null): array
    {
        $payload = ['name' => $name];
        if (is_string($displayName) && $displayName !== '') {
            $payload['attributes'] = ['displayName' => [$displayName]];
        }

        $response = $this->adminRequest()
            ->post($this->adminUrl('/groups'), $payload);

        if ($response->status() === 201) {
            return [
                'id' => $this->extractIdFromLocation($response->header('Location')),
                'name' => $name,
                'path' => '/'.$name,
            ];
        }

        return $this->decodeResponse($response);
    }

    public function deleteGroup(string $groupId): void
    {
        $response = $this->adminRequest()
            ->delete($this->adminUrl('/groups/'.$groupId));

        if (!in_array($response->status(), [204, 200], true)) {
            $response->throw();
        }
    }

    public function createUser(array $payload): array
    {
        $response = $this->adminRequest()
            ->post($this->adminUrl('/users'), $payload);

        if ($response->status() === 201) {
            return [
                'id' => $this->extractIdFromLocation($response->header('Location')),
            ];
        }

        return $this->decodeResponse($response);
    }

    public function updateUser(string $userId, array $payload): void
    {
        $response = $this->adminRequest()
            ->put($this->adminUrl('/users/'.$userId), $payload);

        if (!in_array($response->status(), [204, 200], true)) {
            $response->throw();
        }
    }

    public function deleteUser(string $userId): void
    {
        $response = $this->adminRequest()
            ->delete($this->adminUrl('/users/'.$userId));

        if (in_array($response->status(), [204, 200, 404], true)) {
            return;
        }

        $response->throw();
    }

    public function getClientRole(string $clientUuid, string $roleName): array
    {
        $response = $this->adminRequest()
            ->get($this->adminUrl('/clients/'.$clientUuid.'/roles/'.$roleName));

        return $this->decodeResponse($response);
    }

    public function listClientRoles(string $clientUuid): array
    {
        $response = $this->adminRequest()
            ->get($this->adminUrl('/clients/'.$clientUuid.'/roles'));

        return $this->decodeResponse($response);
    }

    public function findClientRoleByName(string $clientUuid, string $roleName): ?array
    {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return null;
        }

        try {
            return $this->getClientRole($clientUuid, $roleName);
        } catch (\Throwable $exception) {
            // fallthrough to case-insensitive lookup
        }

        $roles = $this->listClientRoles($clientUuid);
        foreach ($roles as $role) {
            $name = $role['name'] ?? null;
            if (is_string($name) && Str::lower($name) === Str::lower($roleName)) {
                return $role;
            }
        }

        return null;
    }

    public function assignClientRolesToUser(string $userId, string $clientUuid, array $roles): void
    {
        $response = $this->adminRequest()
            ->post($this->adminUrl('/users/'.$userId.'/role-mappings/clients/'.$clientUuid), $roles);

        if (!in_array($response->status(), [204, 201], true)) {
            $response->throw();
        }
    }

    public function executeActionsEmail(string $userId, array $actions, ?int $lifespan = null): void
    {
        $params = [];
        if ($lifespan !== null && $lifespan > 0) {
            $params['lifespan'] = $lifespan;
        }

        $url = $this->adminUrl('/users/'.$userId.'/execute-actions-email');
        if ($params) {
            $url .= '?'.http_build_query($params);
        }

        $response = $this->adminRequest()
            ->put($url, $actions);

        if (!in_array($response->status(), [204, 200], true)) {
            $response->throw();
        }
    }

    public function createChildGroup(string $parentId, string $name, ?string $displayName = null): array
    {
        $payload = ['name' => $name];
        if (is_string($displayName) && $displayName !== '') {
            $payload['attributes'] = ['displayName' => [$displayName]];
        }

        $response = $this->adminRequest()
            ->post($this->adminUrl('/groups/'.$parentId.'/children'), $payload);

        if ($response->status() === 201) {
            return [
                'id' => $this->extractIdFromLocation($response->header('Location')),
                'name' => $name,
            ];
        }

        return $this->decodeResponse($response);
    }

    public function findClientUuidByClientId(string $clientId): string
    {
        $cacheKey = 'keycloak.admin.client-uuid.'.Str::lower($clientId);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->adminRequest()
            ->get($this->adminUrl('/clients'), [
                'clientId' => $clientId,
            ]);

        $items = $this->decodeResponse($response);
        $client = collect($items)->firstWhere('clientId', $clientId);

        $uuid = $client['id'] ?? null;
        if (!is_string($uuid) || $uuid === '') {
            throw new RuntimeException('Keycloak client UUID not found.');
        }

        Cache::put($cacheKey, $uuid, now()->addHours(6));

        return $uuid;
    }

    private function adminRequest()
    {
        return $this->http
            ->timeout((int) config('keycloak.admin_timeout', 10))
            ->retry(2, 200)
            ->withToken($this->getAdminToken())
            ->acceptJson();
    }

    private function adminUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('keycloak.base_url'), '/');
        $realm = (string) config('keycloak.realm');
        if (!$baseUrl || !$realm) {
            throw new RuntimeException('Keycloak base URL or realm is missing.');
        }

        return $baseUrl.'/admin/realms/'.$realm.$path;
    }

    private function decodeResponse($response): array
    {
        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Keycloak admin request failed: '.$exception->getMessage());
        }

        $data = $response->json();
        return is_array($data) ? $data : [];
    }

    private function extractIdFromLocation(?string $location): ?string
    {
        if (!$location) {
            return null;
        }

        $parts = explode('/', rtrim($location, '/'));
        return $parts ? end($parts) : null;
    }
}
