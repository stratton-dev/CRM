<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\KeycloakSyncJob;
use App\Models\OrgCounter;
use App\Models\User;
use App\Models\Role;
use App\Services\Structure\HierarchicalCodeService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('structure:import-csv {file} {--delimiter=} {--organization-id=} {--team=} {--prefix-teams} {--dry-run}',
    function () {
        $file = (string) $this->argument('file');
        if ($file === '' || !is_file($file)) {
            $this->error('File not found: '.$file);
            return 1;
        }

        $delimiter = (string) $this->option('delimiter');
        if ($delimiter === '') {
            $firstLine = (string) (file($file, FILE_IGNORE_NEW_LINES)[0] ?? '');
            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        }

        $orgId = $this->option('organization-id');
        $teamOverride = $this->option('team');
        $prefixTeams = (bool) $this->option('prefix-teams');
        $dryRun = (bool) $this->option('dry-run');

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error('Unable to open file.');
            return 1;
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            $this->error('Empty CSV.');
            return 1;
        }

        $normalizeHeader = function ($value): string {
            $value = strtolower(trim((string) $value));
            $value = preg_replace('/\s+/', '_', $value);
            $value = str_replace(['-', '.'], '_', $value);
            return $value;
        };

        $knownHeaders = [
            'code', 'path', 'hierarchical_code', 'hierarchical_id', 'structure_code',
            'role', 'role_cached',
            'first_name', 'last_name', 'name',
            'email', 'phone',
            'team_group_path', 'team', 'team_code',
            'keycloak_id',
            'crm_number',
            'active', 'enabled', 'pending_setup',
            'is_removed_from_structure', 'is_blocked',
        ];

        $headerNormalized = array_map($normalizeHeader, $header);
        $hasKnownHeader = (bool) array_intersect($knownHeaders, $headerNormalized);

        if (!$hasKnownHeader) {
            // No header row; treat first row as data using default column order.
            rewind($handle);
        }

        $defaultOrder = [
            'code',
            'role',
            'first_name',
            'last_name',
            'phone',
            'email',
            'team_group_path',
            'active',
            'enabled',
            'pending_setup',
            'crm_number',
        ];

        $roleMap = [
            'dyrektor' => 'DIRECTOR',
            'director' => 'DIRECTOR',
            'menadzer' => 'MANAGER',
            'manager' => 'MANAGER',
            'sprzedawca' => 'SALES',
            'sales' => 'SALES',
            'doradca' => 'SALES',
            'advisor' => 'SALES',
            'admin' => 'ADMIN',
        ];

        $toBool = function ($value): ?bool {
            if ($value === null) {
                return null;
            }
            $value = strtolower(trim((string) $value));
            if ($value === '') {
                return null;
            }
            if (in_array($value, ['1', 'true', 't', 'yes', 'y', 'tak'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'f', 'no', 'n', 'nie'], true)) {
                return false;
            }
            return null;
        };

        $items = [];
        $rowIndex = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowIndex++;
            if ($row === [null] || $row === false) {
                continue;
            }

            $rowData = [];
            if ($hasKnownHeader) {
                foreach ($headerNormalized as $index => $column) {
                    $rowData[$column] = $row[$index] ?? null;
                }
            } else {
                foreach ($defaultOrder as $index => $column) {
                    $rowData[$column] = $row[$index] ?? null;
                }
            }

            $code = trim((string) ($rowData['code'] ?? $rowData['hierarchical_code'] ?? $rowData['hierarchical_id'] ?? $rowData['path'] ?? ''));
            if ($code === '') {
                $this->warn('Row '.$rowIndex.' skipped: missing code/path.');
                continue;
            }

            $firstName = trim((string) ($rowData['first_name'] ?? ''));
            $lastName = trim((string) ($rowData['last_name'] ?? ''));
            $name = trim((string) ($rowData['name'] ?? ''));
            if ($name === '') {
                $name = trim($firstName.' '.$lastName);
            }
            if ($name === '') {
                $name = 'Unknown';
            }

            $rawRole = strtolower(trim((string) ($rowData['role'] ?? $rowData['role_cached'] ?? '')));
            $roleCached = $roleMap[$rawRole] ?? strtoupper($rawRole);
            if ($roleCached === '') {
                $roleCached = 'SALES';
            }

            $teamGroupPath = trim((string) ($rowData['team_group_path'] ?? $rowData['team'] ?? $rowData['team_code'] ?? ''));
            if ($teamGroupPath === '' && $teamOverride) {
                $teamGroupPath = (string) $teamOverride;
            }
            if ($teamGroupPath !== '' && $prefixTeams && !str_starts_with($teamGroupPath, '/')) {
                $teamGroupPath = '/teams/'.ltrim($teamGroupPath, '/');
            }

            $email = trim((string) ($rowData['email'] ?? '')) ?: null;
            $phone = trim((string) ($rowData['phone'] ?? '')) ?: null;
            $keycloakId = trim((string) ($rowData['keycloak_id'] ?? '')) ?: null;
            $crmNumber = trim((string) ($rowData['crm_number'] ?? '')) ?: null;

            $active = $toBool($rowData['active'] ?? null);
            $enabled = $toBool($rowData['enabled'] ?? null);
            $pendingSetup = $toBool($rowData['pending_setup'] ?? null);
            $isRemoved = $toBool($rowData['is_removed_from_structure'] ?? null);
            $isBlocked = $toBool($rowData['is_blocked'] ?? null);

            $parentCode = null;
            if (str_contains($code, '/')) {
                $parentCode = substr($code, 0, strrpos($code, '/'));
            }

            $items[] = [
                'row' => $rowIndex,
                'code' => $code,
                'parent_code' => $parentCode,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role_cached' => $roleCached,
                'team_group_path' => $teamGroupPath,
                'keycloak_id' => $keycloakId,
                'crm_number' => $crmNumber,
                'active' => $active,
                'enabled' => $enabled,
                'pending_setup' => $pendingSetup,
                'is_removed_from_structure' => $isRemoved,
                'is_blocked' => $isBlocked,
            ];
        }
        fclose($handle);

        if (count($items) === 0) {
            $this->error('No rows to import.');
            return 1;
        }

        $codes = [];
        foreach ($items as $item) {
            if (isset($codes[$item['code']])) {
                $this->error('Duplicate code/path: '.$item['code']);
                return 1;
            }
            $codes[$item['code']] = true;
        }

        $roleByCode = Role::query()->get()->keyBy('code');

        $this->info('Rows to import: '.count($items));
        if ($dryRun) {
            $this->comment('Dry run enabled. No changes were saved.');
            return 0;
        }

        DB::transaction(function () use ($items, $orgId, $roleByCode) {
            $byCode = [];

            foreach ($items as $item) {
                $keycloakId = $item['keycloak_id'] ?: Str::uuid()->toString();

                $roleCode = strtolower($item['role_cached']);
                $roleId = $roleByCode->get($roleCode)->id ?? null;

                $user = User::create([
                    'organization_id' => $orgId ?: null,
                    'role_id' => $roleId,
                    'parent_id' => null,
                    'parent_keycloak_id' => null,
                    'hierarchical_code' => $item['code'],
                    'hierarchical_id' => $item['code'],
                    'crm_number' => $item['crm_number'],
                    'role_cached' => $item['role_cached'],
                    'team_group_path' => $item['team_group_path'],
                    'keycloak_id' => $keycloakId,
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'phone' => $item['phone'],
                    'active' => $item['active'] ?? true,
                    'enabled' => $item['enabled'] ?? true,
                    'pending_setup' => $item['pending_setup'] ?? false,
                    'is_removed_from_structure' => $item['is_removed_from_structure'] ?? false,
                    'is_blocked' => $item['is_blocked'] ?? false,
                    'password' => Str::random(32),
                ]);

                $byCode[$item['code']] = $user;
            }

            foreach ($items as $item) {
                if (!$item['parent_code']) {
                    continue;
                }

                $parent = $byCode[$item['parent_code']] ?? null;
                if (!$parent) {
                    throw new RuntimeException('Missing parent for code '.$item['code']);
                }

                $child = $byCode[$item['code']];
                $child->update([
                    'parent_id' => $parent->id,
                    'parent_keycloak_id' => $parent->keycloak_id,
                ]);
            }
});

        $this->info('Import completed.');
        return 0;
    }
)->purpose('Import hierarchy users from a CSV export (header or default order).');

Artisan::command('crm:purge-clients-offers {--force}', function () {
    $force = (bool) $this->option('force');
    if (!$force) {
        $confirmed = $this->confirm('To polecenie usunie WSZYSTKICH klientów oraz WSZYSTKIE oferty (i powiązane dane). Kontynuować?');
        if (!$confirmed) {
            $this->info('Anulowano.');
            return 0;
        }
    }

    $tables = [
        'offer_verifications',
        'offer_items',
        'offers',
        'payroll_calculations',
        'payroll_items',
        'payrolls',
        'meeting_analysis',
        'meetings',
        'documents',
        'client_consents',
        'client_contacts',
        'crm_client_activities',
        'crm_client_profiles',
        'crm_employees',
        'crm_invoices',
        'crm_saved_offers',
        'employees',
        'companies',
    ];

    DB::beginTransaction();
    try {
        $totalDeleted = 0;
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $count = DB::table($table)->count();
            if ($count > 0) {
                DB::table($table)->delete();
                $this->line(sprintf('Usunieto %d rekordow z %s.', $count, $table));
                $totalDeleted += $count;
            }
        }

        DB::commit();
        $this->info('Zakonczono. Usunieto lacznie '.$totalDeleted.' rekordow.');
        return 0;
    } catch (Throwable $e) {
        DB::rollBack();
        $this->error('Blad podczas usuwania danych: '.$e->getMessage());
        return 1;
    }
})->purpose('Usuwa wszystkich klientow i oferty (oraz powiazane dane CRM).');

Artisan::command('structure:export-team-json {team_group_path} {file?}', function () {
    $teamGroupPath = trim((string) $this->argument('team_group_path'));
    if ($teamGroupPath === '') {
        $this->error('Missing team_group_path.');
        return 1;
    }

    $users = User::query()
        ->where('team_group_path', $teamGroupPath)
        ->orderBy('hierarchical_code')
        ->orderBy('name')
        ->get();

    if ($users->isEmpty()) {
        $this->error('No users found for team: '.$teamGroupPath);
        return 1;
    }

    $byKeycloak = $users->keyBy('keycloak_id');

        $rows = $users->map(function (User $user) use ($byKeycloak) {
            $parentCode = null;
            $parentEmail = null;
            if ($user->parent_keycloak_id && $byKeycloak->has($user->parent_keycloak_id)) {
                $parent = $byKeycloak->get($user->parent_keycloak_id);
                $parentCode = $parent?->hierarchical_code ?: $parent?->hierarchical_id;
                $parentEmail = $parent?->email;
            }

        return [
            'code' => $user->hierarchical_code ?: $user->hierarchical_id,
            'parent_code' => $parentCode,
            'keycloak_id' => $user->keycloak_id,
            'parent_keycloak_id' => $user->parent_keycloak_id,
            'parent_email' => $parentEmail,
            'role' => $user->role_cached,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'team_group_path' => $user->team_group_path,
            'crm_number' => $user->crm_number,
            'active' => $user->active,
            'enabled' => $user->enabled,
            'pending_setup' => $user->pending_setup,
            'contract_status' => $user->contract_status,
            'is_removed_from_structure' => $user->is_removed_from_structure,
            'is_blocked' => $user->is_blocked,
        ];
    })->values()->all();

    $payload = [
        'team_group_path' => $teamGroupPath,
        'exported_at' => now()->toIso8601String(),
        'users' => $rows,
    ];

    $fileArg = (string) $this->argument('file');
    if ($fileArg !== '') {
        $path = $fileArg;
    } else {
        $baseDir = __DIR__.'/../exports';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
        $teamCode = trim((string) (strrchr($teamGroupPath, '/') ?: $teamGroupPath), '/');
        $teamCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $teamCode);
        $filename = 'team-'.$teamCode.'-'.now()->format('Ymd_His').'.json';
        $path = $baseDir.DIRECTORY_SEPARATOR.$filename;
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $this->error('Failed to encode JSON.');
        return 1;
    }

    if (file_put_contents($path, $json) === false) {
        $this->error('Failed to write file: '.$path);
        return 1;
    }

    $this->info('Exported '.count($rows).' users to '.$path);
    return 0;
})->purpose('Export team users to JSON for import without extra packages.');

Artisan::command('structure:export-team-json-full {team_group_path} {file?}', function () {
    $teamGroupPath = trim((string) $this->argument('team_group_path'));
    if ($teamGroupPath === '') {
        $this->error('Missing team_group_path.');
        return 1;
    }

    $users = User::query()
        ->where('team_group_path', $teamGroupPath)
        ->orderBy('hierarchical_code')
        ->orderBy('name')
        ->get();

    if ($users->isEmpty()) {
        $this->error('No users found for team: '.$teamGroupPath);
        return 1;
    }

    $payload = [
        'team_group_path' => $teamGroupPath,
        'exported_at' => now()->toIso8601String(),
        'users' => $users->map(function (User $user) {
            $data = $user->toArray();
            unset(
                $data['password'],
                $data['remember_token'],
                $data['sync_error'],
                $data['created_at'],
                $data['updated_at'],
                $data['email_verified_at'],
                $data['last_synced_at']
            );
            return $data;
        })->values()->all(),
    ];

    $fileArg = (string) $this->argument('file');
    if ($fileArg !== '') {
        $path = $fileArg;
    } else {
        $baseDir = __DIR__.'/../exports';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
        $teamCode = trim((string) (strrchr($teamGroupPath, '/') ?: $teamGroupPath), '/');
        $teamCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $teamCode);
        $filename = 'team-'.$teamCode.'-full-'.now()->format('Ymd_His').'.json';
        $path = $baseDir.DIRECTORY_SEPARATOR.$filename;
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $this->error('Failed to encode JSON.');
        return 1;
    }

    if (file_put_contents($path, $json) === false) {
        $this->error('Failed to write file: '.$path);
        return 1;
    }

    $this->info('Exported '.count($payload['users']).' users to '.$path);
    return 0;
})->purpose('Export team users to JSON (sensible fields, without secrets).');

Artisan::command('structure:regenerate-codes {--reset-counters} {--dry-run}', function () {
    $reset = (bool) $this->option('reset-counters');
    $dryRun = (bool) $this->option('dry-run');

    /** @var HierarchicalCodeService $codes */
    $codes = app(HierarchicalCodeService::class);

    if ($reset && !$dryRun) {
        OrgCounter::query()->delete();
    }

    $users = User::query()
        ->whereNotNull('team_group_path')
        ->where('role_cached', '!=', 'ADMIN')
        ->get();

    $byId = $users->keyBy('keycloak_id');
    $childrenByParent = [];
    foreach ($users as $user) {
        $parentKey = $user->parent_keycloak_id;
        $childrenByParent[$parentKey][] = $user;
    }

    foreach ($childrenByParent as &$list) {
        usort($list, function (User $a, User $b): int {
            return [$a->name, $a->keycloak_id] <=> [$b->name, $b->keycloak_id];
        });
    }
    unset($list);

    $roots = $users->filter(function (User $user) use ($byId): bool {
        if (!$user->parent_keycloak_id) {
            return true;
        }
        return !$byId->has($user->parent_keycloak_id);
    })->values();

    $roots = $roots->sortBy([
        fn (User $user) => $user->team_group_path,
        fn (User $user) => $user->name,
        fn (User $user) => $user->keycloak_id,
    ])->values();

    $initialsFromName = function (?string $name): string {
        $name = trim((string) $name);
        if ($name === '') {
            return 'XX';
        }

        $parts = array_values(array_filter(preg_split('/\s+/', $name)));
        if (count($parts) === 1) {
            return Str::upper(mb_substr($parts[0], 0, 2));
        }

        $initials = '';
        foreach ($parts as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return Str::upper($initials);
    };

    $updates = [];
    $walk = function (User $user, ?string $parentCode) use (&$walk, $childrenByParent, $codes, $initialsFromName, &$updates): void {
        $teamPath = $user->team_group_path;
        if (!$teamPath) {
            return;
        }

        $code = $codes->generate($teamPath, $parentCode, $initialsFromName($user->name));
        $updates[$user->keycloak_id] = $code;

        $children = $childrenByParent[$user->keycloak_id] ?? [];
        foreach ($children as $child) {
            $walk($child, $code);
        }
    };

    foreach ($roots as $root) {
        $walk($root, null);
    }

    $this->info('Planned updates: '.count($updates));

    if ($dryRun) {
        $this->comment('Dry run enabled. No changes were saved.');
        return;
    }

    \DB::transaction(function () use ($updates): void {
        foreach ($updates as $keycloakId => $code) {
            User::query()
                ->where('keycloak_id', $keycloakId)
                ->update([
                    'hierarchical_code' => $code,
                    'hierarchical_id' => $code,
                ]);
        }
    });

    $this->info('Regeneration completed.');
})->purpose('Regenerate hierarchical codes for structure users.');

Schedule::job(new KeycloakSyncJob())
    ->everyThirtyMinutes()
    ->when(fn () => (bool) config('keycloak.sync_enabled'));
