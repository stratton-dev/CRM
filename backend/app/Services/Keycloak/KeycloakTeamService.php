<?php

namespace App\Services\Keycloak;

use Illuminate\Support\Str;
use RuntimeException;

class KeycloakTeamService
{
    private const CACHE_KEY = 'keycloak.teams.paths';

    public function __construct(private readonly KeycloakAdminClient $client)
    {
    }

    public function listTeams(bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = cache()->get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $rootPath = $this->normalizePath((string) config('keycloak.teams_root', '/teams'));
        $paths = $this->collectTeamPaths($rootPath);

        cache()->put(self::CACHE_KEY, $paths, now()->addMinutes(30));

        return $paths;
    }

    public function refreshTeams(): array
    {
        return $this->listTeams(true);
    }

    public function listTeamGroups(bool $refresh = false): array
    {
        $rootPath = $this->normalizePath((string) config('keycloak.teams_root', '/teams'));
        return $this->collectTeamGroups($rootPath);
    }

    public function createTeam(string $code, ?string $displayName = null): array
    {
        $rootPath = $this->normalizePath((string) config('keycloak.teams_root', '/teams'));
        $teamCode = trim($code);
        if ($teamCode === '' || str_contains($teamCode, '/')) {
            throw new RuntimeException('Invalid team code.');
        }

        $teamPath = rtrim($rootPath, '/').'/'.$teamCode;
        if ($this->findGroupByPath($teamPath)) {
            throw new RuntimeException('Team already exists.');
        }

        $rootGroup = $this->findGroupByPath($rootPath);
        if (!$rootGroup) {
            $rootGroup = $this->client->createGroup(trim($rootPath, '/'));
        }

        $rootId = (string) ($rootGroup['id'] ?? '');
        if ($rootId === '') {
            throw new RuntimeException('Keycloak teams root group is missing.');
        }

        $created = $this->client->createChildGroup($rootId, $teamCode, $displayName);
        $id = $created['id'] ?? null;
        if (!$id) {
            throw new RuntimeException('Failed to create team group in Keycloak.');
        }

        $this->refreshTeams();

        return [
            'id' => $id,
            'code' => $teamCode,
            'path' => $teamPath,
            'displayName' => $displayName,
        ];
    }

    public function deleteTeam(string $path): array
    {
        $rootPath = $this->normalizePath((string) config('keycloak.teams_root', '/teams'));
        $rawPath = trim($path);
        $teamPath = $this->normalizeTeamPathCandidate($rawPath);
        $prefix = rtrim($rootPath, '/').'/';
        $pathsToTry = array_values(array_unique(array_filter([
            $teamPath,
            $rootPath.'/'.trim($rawPath, '/'),
        ])));

        $groupId = null;
        $resolvedPath = null;
        foreach ($pathsToTry as $candidate) {
            if ($candidate === $rootPath) {
                continue;
            }
            if (!str_starts_with($candidate, $prefix)) {
                continue;
            }
            $found = $this->findTeamGroupByPath($candidate);
            if ($found) {
                $groupId = $found['id'] ?? null;
                $resolvedPath = $found['path'] ?? null;
                break;
            }
        }

        if (!$groupId) {
            $code = trim(strrchr($teamPath, '/') ?: $teamPath, '/');
            if ($code !== '') {
                $group = $this->findGroupByName($code, $prefix);
                if ($group) {
                    $groupId = $group['id'] ?? null;
                    $resolvedPath = $group['path'] ?? null;
                }
            }
        }

        if (!$groupId || !$resolvedPath) {
            throw new RuntimeException('Team group not found in Keycloak.');
        }
        if ($resolvedPath === $rootPath) {
            throw new RuntimeException('Cannot delete teams root group.');
        }

        $members = $this->client->listGroupMembers($groupId, 0, 1);
        if ($members) {
            throw new RuntimeException('Team has assigned users.');
        }

        $children = $this->client->listGroupChildren($groupId, 0, 1);
        if ($children) {
            throw new RuntimeException('Team has child groups.');
        }

        $this->client->deleteGroup($groupId);
        $this->refreshTeams();

        return [
            'id' => $groupId,
            'path' => $resolvedPath,
        ];
    }

    private function findTeamGroupByPath(string $path): ?array
    {
        $path = $this->normalizePath($path);
        $groups = $this->listTeamGroups(true);
        foreach ($groups as $group) {
            $groupPath = $group['path'] ?? null;
            if (is_string($groupPath) && $groupPath === $path) {
                return $group;
            }
        }

        $id = $this->findGroupIdByPath($path);
        return $id ? ['id' => $id, 'path' => $path] : null;
    }

    private function normalizeTeamPathCandidate(string $path): string
    {
        $clean = preg_replace('/^team:/', '', trim($path));
        if (!str_starts_with($clean, '/')) {
            $clean = '/'.$clean;
        }
        return $this->normalizePath($clean);
    }

    private function findGroupByName(string $name, ?string $prefix = null): ?array
    {
        $groups = $this->client->listAllGroups();
        return $this->findByName($groups, $name, $prefix);
    }

    private function findByName(array $groups, string $name, ?string $prefix): ?array
    {
        foreach ($groups as $group) {
            $groupName = $group['name'] ?? null;
            $groupPath = $group['path'] ?? null;
            if (is_string($groupName) && $groupName === $name) {
                if (!$prefix || (is_string($groupPath) && str_starts_with($groupPath, $prefix))) {
                    return $group;
                }
            }

            $children = $group['subGroups'] ?? [];
            if (is_array($children) && $children !== []) {
                $found = $this->findByName($children, $name, $prefix);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    public function moveUserToTeam(string $userKeycloakId, string $teamPath): void
    {
        $rootPath = $this->normalizePath((string) config('keycloak.teams_root', '/teams'));
        $teamPath = $this->normalizePath($teamPath);
        $prefix = rtrim($rootPath, '/').'/';
        if (!str_starts_with($teamPath, $prefix)) {
            throw new RuntimeException('Target team path is outside teams root.');
        }

        $groups = $this->client->getUserGroups($userKeycloakId);
        $currentTeamGroups = array_values(array_filter($groups, static function (array $group) use ($prefix): bool {
            $path = $group['path'] ?? null;
            return is_string($path) && str_starts_with($path, $prefix);
        }));

        $alreadyMember = false;
        foreach ($currentTeamGroups as $group) {
            $path = $group['path'] ?? null;
            if ($path === $teamPath) {
                $alreadyMember = true;
                continue;
            }

            $groupId = $group['id'] ?? null;
            if (!is_string($groupId) || $groupId === '') {
                $groupId = $this->findGroupIdByPath($path ?? '');
            }

            if (is_string($groupId) && $groupId !== '') {
                $this->client->removeUserFromGroup($userKeycloakId, $groupId);
            }
        }

        if (!$alreadyMember) {
            $targetGroupId = $this->findTeamGroupIdByPath($teamPath);
            $this->client->addUserToGroup($userKeycloakId, $targetGroupId);
        }
    }

    private function findGroupByPath(string $path): ?array
    {
        $groups = $this->client->listGroups(0, (int) config('keycloak.admin_page_size', 100));
        return $this->findInTree($groups, $path);
    }

    private function findTeamGroupIdByPath(string $path): string
    {
        $path = $this->normalizePath($path);
        $groups = $this->listTeamGroups(true);
        foreach ($groups as $group) {
            $groupPath = $group['path'] ?? null;
            if (is_string($groupPath) && $groupPath === $path) {
                $groupId = $group['id'] ?? null;
                if (is_string($groupId) && $groupId !== '') {
                    return $groupId;
                }
            }
        }

        $fallback = $this->findGroupIdByPath($path);
        if ($fallback) {
            return $fallback;
        }

        throw new RuntimeException('Team group not found in Keycloak.');
    }

    private function findInTree(array $groups, string $path): ?array
    {
        foreach ($groups as $group) {
            $groupPath = $group['path'] ?? null;
            if (is_string($groupPath) && $groupPath === $path) {
                return $group;
            }

            $children = $group['subGroups'] ?? [];
            if (is_array($children) && $children !== []) {
                $found = $this->findInTree($children, $path);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $clean = '/'.ltrim($path, '/');
        return rtrim($clean, '/');
    }

    private function collectTeamPaths(string $rootPath): array
    {
        $items = $this->collectTeamGroups($rootPath);
        $paths = array_map(static fn (array $item): string => $item['path'], $items);

        $paths = array_values(array_unique(array_filter($paths)));
        sort($paths);

        return $paths;
    }

    private function collectTeamGroups(string $rootPath): array
    {
        $rootId = $this->findGroupIdByPath($rootPath);
        if ($rootId) {
            $items = [];
            $this->collectChildrenViaApi($rootId, $rootPath, $items);
            return $items;
        }

        $groups = $this->client->listAllGroups();
        $items = [];
        $this->collectGroups($groups, $items);
        $prefix = rtrim($rootPath, '/').'/';

        return array_values(array_filter($items, static function (array $item) use ($prefix): bool {
            $path = $item['path'] ?? null;
            return is_string($path) && str_starts_with($path, $prefix);
        }));
    }

    private function findGroupIdByPath(string $path): ?string
    {
        $groups = $this->client->listAllGroups();
        $found = $this->findInTree($groups, $path);
        $id = $found['id'] ?? null;
        return is_string($id) && $id !== '' ? $id : null;
    }

    private function collectChildrenViaApi(string $groupId, string $parentPath, array &$items): void
    {
        $first = 0;
        $pageSize = (int) config('keycloak.admin_page_size', 100);

        while (true) {
            $children = $this->client->listGroupChildren($groupId, $first, $pageSize);
            if (!$children) {
                break;
            }

            foreach ($children as $child) {
                $id = $child['id'] ?? null;
                $path = $child['path'] ?? null;
                $name = $child['name'] ?? null;
                if (!is_string($path) || $path === '') {
                    $path = is_string($name) ? rtrim($parentPath, '/').'/'.$name : null;
                }

                if (is_string($id) && $id !== '' && is_string($path) && $path !== '') {
                    $items[] = ['id' => $id, 'path' => $path];
                    $this->collectChildrenViaApi($id, $path, $items);
                }
            }

            if (count($children) < $pageSize) {
                break;
            }

            $first += $pageSize;
        }
    }

    private function collectPaths(array $groups, array &$paths): void
    {
        foreach ($groups as $group) {
            $path = $group['path'] ?? null;
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }

            $children = $group['subGroups'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->collectPaths($children, $paths);
            }
        }
    }

    private function collectGroups(array $groups, array &$items): void
    {
        foreach ($groups as $group) {
            $id = $group['id'] ?? null;
            $path = $group['path'] ?? null;
            if (is_string($id) && $id !== '' && is_string($path) && $path !== '') {
                $items[] = ['id' => $id, 'path' => $path];
            }

            $children = $group['subGroups'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->collectGroups($children, $items);
            }
        }
    }
}
