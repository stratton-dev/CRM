<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Structure\HierarchicalCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StructureUsersImportSeeder extends Seeder
{
    public function run(): void
    {
        $file = (string) env('STRUCTURE_IMPORT_FILE', '');
        if ($file === '') {
            $file = $this->promptForFile();
        }

        if (!is_file($file)) {
            $this->command?->error('Structure import file not found: '.$file);
            return;
        }

        $delimiter = (string) env('STRUCTURE_IMPORT_DELIMITER', '');
        $orgId = env('STRUCTURE_IMPORT_ORGANIZATION_ID');
        $teamOverride = env('STRUCTURE_IMPORT_TEAM');
        $prefixTeams = filter_var(env('STRUCTURE_IMPORT_PREFIX_TEAMS', false), FILTER_VALIDATE_BOOLEAN);
        $sheetIndex = (int) env('STRUCTURE_IMPORT_SHEET', 0);

        $rows = $this->readRows($file, $delimiter, $sheetIndex);
        if ($rows === []) {
            $this->command?->error('No rows to import.');
            return;
        }

        $rawById = [];
        $rawByEmail = [];
        if ($this->isAssoc($rows[0] ?? [])) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = $row['id'] ?? null;
                if (is_numeric($id) || (is_string($id) && $id !== '')) {
                    $rawById[(string) $id] = $row;
                }
                $email = $row['email'] ?? null;
                if (is_string($email) && $email !== '') {
                    $rawByEmail[strtolower($email)] = $row;
                }
            }
        }

        $knownHeaders = [
            'code', 'parent_code', 'path', 'hierarchical_code', 'hierarchical_id', 'structure_code',
            'role', 'role_cached',
            'first_name', 'last_name', 'name',
            'email', 'phone',
            'team_group_path', 'team', 'team_code',
            'keycloak_id',
            'parent_id',
            'parent_keycloak_id', 'parentkeycloakid',
            'parent_email', 'parentemail',
            'crm_number',
            'active', 'enabled', 'pending_setup',
            'is_removed_from_structure', 'is_blocked',
            'lp', 'id_hierarhical', 'id_hierarchical', 'stanowisko', 'imie', 'nazwisko',
            'nr_telefonu', 'adres_email', 'adres_e_mail', 'adres_email', 'aders_email', 'aders_e_mail', 'osoba_nadrzedna',
            'umowa_podpisana', 'wiadomosc_powitalna', 'dostepy_nadane', 'nr_tel_do_bramki_sms',
            'contract_status', 'contractstatus',
        ];

        $isAssocRows = $this->isAssoc($rows[0] ?? []);
        $isFullExport = $isAssocRows && $this->looksLikeFullExport($rows[0] ?? []);
        $header = $isAssocRows ? [] : array_map([$this, 'normalizeHeader'], $rows[0]);
        $hasKnownHeader = $isAssocRows ? true : (bool) array_intersect($knownHeaders, $header);

        $startRow = 0;
        if ($hasKnownHeader && !$isAssocRows) {
            $startRow = 1;
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
            'menedzer' => 'MANAGER',
            'manager' => 'MANAGER',
            'handlowiec' => 'SALES',
            'sprzedawca' => 'SALES',
            'sales' => 'SALES',
            'doradca' => 'SALES',
            'advisor' => 'SALES',
            'admin' => 'ADMIN',
        ];

        $items = [];
        $parentCodes = [];
        $sourceIds = [];
        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!$isAssocRows && $this->isEmptyRow($row)) {
                continue;
            }

            $rowData = [];
            if ($isAssocRows) {
                foreach ($row as $key => $value) {
                    $rowData[$this->normalizeHeader($key)] = $value;
                }
            } elseif ($hasKnownHeader) {
                foreach ($header as $index => $column) {
                    $rowData[$column] = $row[$index] ?? null;
                }
            } else {
                foreach ($defaultOrder as $index => $column) {
                    $rowData[$column] = $row[$index] ?? null;
                }
            }

            $code = $this->normalizeCode((string) ($rowData['code']
                ?? $rowData['hierarchical_code']
                ?? $rowData['hierarchical_id']
                ?? $rowData['path']
                ?? $rowData['structure_code']
                ?? $rowData['id_hierarhical']
                ?? $rowData['id_hierarchical']
                ?? ''));
            if ($code === '' && !$isFullExport) {
                $this->command?->warn('Row '.($i + 1).' skipped: missing code/path.');
                continue;
            }

            $firstName = trim((string) ($rowData['first_name'] ?? $rowData['imie'] ?? ''));
            $lastName = trim((string) ($rowData['last_name'] ?? $rowData['nazwisko'] ?? ''));
            $name = trim((string) ($rowData['name'] ?? ''));
            if ($name === '') {
                $name = trim($firstName.' '.$lastName);
            }
            if ($name === '') {
                $name = 'Unknown';
            }

            $rawRole = $this->normalizeRoleValue((string) ($rowData['role'] ?? $rowData['role_cached'] ?? $rowData['stanowisko'] ?? ''));
            $roleCached = $rowData['role_cached'] ?? null;
            if (!is_string($roleCached) || trim($roleCached) === '') {
                $roleCached = $roleMap[$rawRole] ?? strtoupper($rawRole);
            }
            if ($roleCached === '') {
                $roleCached = 'SALES';
            }

            $teamGroupPath = trim((string) ($rowData['team_group_path'] ?? $rowData['team'] ?? $rowData['team_code'] ?? ''));
            if ($teamGroupPath === '' && $teamOverride) {
                $teamGroupPath = (string) $teamOverride;
            }
            if ($teamGroupPath === '') {
                $teamGroupPath = $this->rootFromCode($code);
            }
            if ($teamGroupPath !== '' && !str_starts_with($teamGroupPath, '/')) {
                $teamGroupPath = '/teams/'.ltrim($teamGroupPath, '/');
            }

            $email = trim((string) ($rowData['email']
                ?? $rowData['adres_email']
                ?? $rowData['adres_e_mail']
                ?? $rowData['aders_email']
                ?? $rowData['aders_e_mail']
                ?? '')) ?: null;
            $phone = trim((string) ($rowData['phone'] ?? $rowData['nr_telefonu'] ?? $rowData['nr_tel_do_bramki_sms'] ?? '')) ?: null;
            $keycloakId = trim((string) ($rowData['keycloak_id'] ?? '')) ?: null;
            $parentKeycloakId = trim((string) ($rowData['parent_keycloak_id'] ?? $rowData['parentkeycloakid'] ?? '')) ?: null;
            $parentEmail = trim((string) ($rowData['parent_email'] ?? $rowData['parentemail'] ?? '')) ?: null;
            if (!$parentKeycloakId && !empty($rowData['parent_id'])) {
                $parentRow = $rawById[(string) $rowData['parent_id']] ?? null;
                if (is_array($parentRow) && !empty($parentRow['keycloak_id'])) {
                    $parentKeycloakId = trim((string) $parentRow['keycloak_id']);
                }
            }
            if (!$parentKeycloakId && $parentEmail !== '') {
                $parentRow = $rawByEmail[strtolower($parentEmail)] ?? null;
                if (is_array($parentRow) && !empty($parentRow['keycloak_id'])) {
                    $parentKeycloakId = trim((string) $parentRow['keycloak_id']);
                }
            }
            $crmNumber = trim((string) ($rowData['crm_number'] ?? '')) ?: null;

            $active = $this->toBool($rowData['active'] ?? $rowData['umowa_podpisana'] ?? null);
            $enabled = $this->toBool($rowData['enabled'] ?? $rowData['dostepy_nadane'] ?? null);
            $pendingSetup = $this->toBool($rowData['pending_setup'] ?? $rowData['wiadomosc_powitalna'] ?? null);
            $isRemoved = $this->toBool($rowData['is_removed_from_structure'] ?? null);
            $isBlocked = $this->toBool($rowData['is_blocked'] ?? null);
            $contractStatus = $this->normalizeContractStatus(
                $rowData['contract_status'] ?? $rowData['contractstatus'] ?? null,
                $rowData['umowa_podpisana'] ?? null
            );

            $parentCode = $this->normalizeCode((string) ($rowData['parent_code'] ?? $rowData['osoba_nadrzedna'] ?? ''));
            if (!$isFullExport) {
                if ($parentCode === '' && str_contains($code, '/')) {
                    $parentCode = substr($code, 0, strrpos($code, '/'));
                }
                if ($parentCode === $code) {
                    $parentCode = '';
                }
                if ($parentCode !== '') {
                    $parentCodes[$parentCode] = true;
                }
            }

            $sourceId = null;
            $sourceParentId = null;
            if ($isFullExport) {
                $sourceId = trim((string) ($rowData['id'] ?? ''));
                if ($sourceId === '') {
                    $this->command?->warn('Row '.($i + 1).' skipped: missing source id.');
                    continue;
                }
                $sourceParentId = trim((string) ($rowData['parent_id'] ?? '')) ?: null;
                if (isset($sourceIds[$sourceId])) {
                    throw new RuntimeException('Duplicate source id detected: '.$sourceId);
                }
                $sourceIds[$sourceId] = true;
            }

            $items[] = [
                'row' => $i + 1,
                'row_id' => trim((string) ($rowData['lp'] ?? '')),
                'code' => $code,
                'parent_code' => $parentCode,
                'source_id' => $sourceId,
                'source_parent_id' => $sourceParentId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role_cached' => $roleCached,
                'team_group_path' => $teamGroupPath,
                'keycloak_id' => $keycloakId,
                'parent_keycloak_id' => $parentKeycloakId,
                'parent_email' => $parentEmail,
                'crm_number' => $crmNumber,
                'active' => $active,
                'enabled' => $enabled,
                'pending_setup' => $pendingSetup,
                'is_removed_from_structure' => $isRemoved,
                'is_blocked' => $isBlocked,
                'contract_status' => $contractStatus,
                'local_keycloak_id' => $isFullExport && $sourceId ? 'local-'.$sourceId : null,
            ];
        }

        if ($items === []) {
            $this->command?->error('No rows to import after parsing.');
            return;
        }

        if (!$isFullExport) {
            $codes = [];
            foreach ($items as $item) {
                if (!isset($codes[$item['code']])) {
                    $codes[$item['code']] = 1;
                    continue;
                }
                $codes[$item['code']]++;
                if (isset($parentCodes[$item['code']])) {
                    throw new RuntimeException('Duplicate parent code detected: '.$item['code']);
                }
            }

            foreach ($items as &$item) {
                if ($item['parent_code'] !== '' && !isset($codes[$item['parent_code']])) {
                    $inferred = $this->inferParentFromCode($item['code']);
                    if ($inferred !== '' && isset($codes[$inferred])) {
                        $this->command?->warn('Row '.$item['row'].': parent code '.$item['parent_code'].' not found, using inferred '.$inferred.'.');
                        $item['parent_code'] = $inferred;
                    } else {
                        $this->command?->warn('Row '.$item['row'].': parent code '.$item['parent_code'].' not found, setting as root.');
                        $item['parent_code'] = '';
                    }
                }
            }
            unset($item);
        }

        $roleByCode = Role::query()->get()->keyBy(fn (Role $role) => strtolower((string) $role->code));
        $missingRoleIds = [];

        $this->command?->info('Rows to import: '.count($items));

        $codes = app(HierarchicalCodeService::class);

        DB::transaction(function () use ($items, $orgId, $roleByCode, $codes, $isFullExport) {
            $byCode = [];
            $byKeycloakId = [];
            $bySourceId = [];

            $remaining = array_values($items);
            $guard = 0;
            while ($remaining !== []) {
                $guard++;
                if ($guard > count($items) + 5) {
                    throw new RuntimeException('Unable to resolve hierarchy order. Check parent codes.');
                }

                $progress = 0;
                $nextRemaining = [];

                foreach ($remaining as $item) {
                    if ($item['team_group_path'] === '') {
                        throw new RuntimeException('Missing team_group_path for code '.$item['code']);
                    }

                    $parent = null;
                    if ($isFullExport) {
                        if (!empty($item['source_parent_id'])) {
                            $parent = $bySourceId[$item['source_parent_id']] ?? null;
                            if (!$parent) {
                                $nextRemaining[] = $item;
                                continue;
                            }
                        }
                    } else {
                        if (!empty($item['parent_keycloak_id'])) {
                            $parent = $byKeycloakId[$item['parent_keycloak_id']] ?? null;
                            if (!$parent) {
                                $parent = User::query()->where('keycloak_id', $item['parent_keycloak_id'])->first();
                                if ($parent) {
                                    $byKeycloakId[$item['parent_keycloak_id']] = $parent;
                                }
                            }
                            if (!$parent) {
                                $nextRemaining[] = $item;
                                continue;
                            }
                        } elseif (!empty($item['parent_email'])) {
                            $parent = User::query()->where('email', $item['parent_email'])->first();
                            if (!$parent) {
                                $parent = null;
                                $nextRemaining[] = $item;
                                continue;
                            }
                        } elseif ($item['parent_code'] !== '') {
                            $parent = $byCode[$item['parent_code']] ?? null;
                            if (!$parent) {
                                $nextRemaining[] = $item;
                                continue;
                            }
                        }
                    }

                    $keycloakId = $item['local_keycloak_id'] ?: $item['keycloak_id'] ?: Str::uuid()->toString();
                    $roleCode = strtolower($item['role_cached']);
                    $roleId = $roleByCode->get($roleCode)->id ?? null;
                    if ($roleId === null) {
                        $missingRoleIds[$roleCode] = true;
                    }

                    $email = $item['email'];
                    if (!$email) {
                        $seed = $item['code'].'|'.$item['row_id'].'|'.$item['name'];
                        $email = 'import+'.md5($seed).'@local.invalid';
                    }

                    $existing = null;
                    if (!empty($item['local_keycloak_id'])) {
                        $existing = User::query()->where('keycloak_id', $item['local_keycloak_id'])->first();
                    }
                    if (!$existing && !empty($item['keycloak_id'])) {
                        $existing = User::query()->where('keycloak_id', $item['keycloak_id'])->first();
                    }
                    if (!$existing && $email) {
                        $existing = User::query()->where('email', $email)->first();
                    }
                    if (!$existing && $item['code'] !== '') {
                        $existing = User::query()->where('hierarchical_code', $item['code'])->first();
                    }

                    $hierarchicalCode = $item['code'] ?: $codes->generate(
                        $item['team_group_path'],
                        $parent?->hierarchical_code,
                        $this->initialsFromName($item['name'])
                    );

                    $payload = [
                        'organization_id' => $orgId ?: null,
                        'role_id' => $roleId,
                        'parent_id' => $parent?->id,
                        'parent_keycloak_id' => $parent?->keycloak_id ?? null,
                        'hierarchical_code' => $hierarchicalCode,
                        'hierarchical_id' => $hierarchicalCode,
                        'crm_number' => $item['crm_number'],
                        'role_cached' => $item['role_cached'],
                        'team_group_path' => $item['team_group_path'],
                        'keycloak_id' => $keycloakId,
                        'keycloak_username' => null,
                        'name' => $item['name'],
                        'email' => $item['email'] ?: ($existing?->email ?? $email),
                        'phone' => $item['phone'],
                        'active' => $item['active'] ?? true,
                        'enabled' => $item['enabled'] ?? true,
                        'pending_setup' => $item['pending_setup'] ?? false,
                        'is_removed_from_structure' => $item['is_removed_from_structure'] ?? false,
                        'is_blocked' => $item['is_blocked'] ?? false,
                        'contract_status' => $item['contract_status'],
                        'last_synced_at' => null,
                        'sync_error' => null,
                    ];

                    if ($existing) {
                        $existing->fill($payload)->save();
                        $user = $existing->refresh();
                    } else {
                        $payload['password'] = Str::random(32);
                        $user = User::create($payload);
                    }

                    if (!isset($byCode[$item['code']])) {
                        $byCode[$item['code']] = $user;
                    }
                    if ($user->keycloak_id && !isset($byKeycloakId[$user->keycloak_id])) {
                        $byKeycloakId[$user->keycloak_id] = $user;
                    }
                    if ($isFullExport && !empty($item['source_id']) && !isset($bySourceId[$item['source_id']])) {
                        $bySourceId[$item['source_id']] = $user;
                    }
                    $progress++;
                }

                if ($progress === 0) {
                    $examples = [];
                    foreach (array_slice($nextRemaining, 0, 10) as $item) {
                        $examples[] = $item['code'].' -> '.$item['parent_code'];
                    }
                    $hint = $examples ? ' Examples: '.implode(', ', $examples) : '';
                    throw new RuntimeException('Unable to resolve hierarchy order. Check parent codes.'.$hint);
                }

                $remaining = $nextRemaining;
            }
        });

        if ($missingRoleIds !== []) {
            $this->command?->warn('Missing roles in roles table for: '.implode(', ', array_keys($missingRoleIds)));
        }

        $this->command?->info('Structure import completed.');
    }

    private function normalizeHeader($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(
            ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ż', 'ź'],
            ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'],
            $value
        );
        $value = preg_replace('/\s+/', '_', $value);
        $value = str_replace(['-', '.'], '_', $value);
        $value = str_replace('__', '_', $value);
        return $value;
    }

    private function normalizeRoleValue(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(
            ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ż', 'ź'],
            ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'],
            $value
        );
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }

    private function toBool($value): ?bool
    {
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
    }

    private function contractStatusFromValue($value): string
    {
        if ($value === null) {
            return 'DRAFT';
        }
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return 'DRAFT';
        }
        if (in_array($raw, ['1', 'true', 't', 'yes', 'y', 'tak', 'signed'], true)) {
            return 'SIGNED';
        }
        return 'DRAFT';
    }

    private function normalizeContractStatus($statusValue, $signedValue): string
    {
        $raw = strtolower(trim((string) ($statusValue ?? '')));
        if ($raw !== '') {
            if (in_array($raw, ['signed', 'podpisana', 'podpisany'], true)) {
                return 'SIGNED';
            }
            if (in_array($raw, ['draft', 'robocza', 'niepodpisana', 'nie_podpisana'], true)) {
                return 'DRAFT';
            }
        }
        return $this->contractStatusFromValue($signedValue);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function readRows(string $file, string $delimiter, int $sheetIndex): array
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            $raw = file_get_contents($file);
            if ($raw === false) {
                throw new RuntimeException('Unable to read file.');
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid JSON format.');
            }
            if (isset($decoded['users']) && is_array($decoded['users'])) {
                return $decoded['users'];
            }
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }
            return $decoded;
        }
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new RuntimeException('PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $sheet = $spreadsheet->getSheet($sheetIndex);
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $rows = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $values = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $value = $sheet->getCell([$col, $row])->getFormattedValue();
                    $values[] = is_string($value) ? trim($value) : $value;
                }
                $rows[] = $values;
            }

            return $rows;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to open file.');
        }

        if ($delimiter === '') {
            $firstLine = (string) (file($file, FILE_IGNORE_NEW_LINES)[0] ?? '');
            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function rootFromCode(string $code): string
    {
        if ($code === '') {
            return '';
        }
        if (!str_contains($code, '/')) {
            return $code;
        }
        return substr($code, 0, strpos($code, '/'));
    }

    private function initialsFromName(?string $name): string
    {
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
    }

    private function promptForFile(): string
    {
        $baseDir = realpath(__DIR__.'/../sources') ?: null;
        if (!$baseDir || !is_dir($baseDir)) {
            $this->command?->warn('STRUCTURE_IMPORT_FILE is empty and sources directory was not found at api/database/sources. Skipping.');
            return '';
        }

        $files = [];
        foreach (['*.xlsx', '*.xls', '*.csv', '*.json'] as $pattern) {
            $matches = glob($baseDir.DIRECTORY_SEPARATOR.$pattern);
            if ($matches) {
                foreach ($matches as $match) {
                    $files[] = $match;
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        if ($files === []) {
            $this->command?->warn('No import files found in ../sources.');
            return '';
        }

        $choices = array_map(function (string $path) use ($baseDir): string {
            return ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);
        }, $files);

        $picked = $this->command?->choice('Select import file from api/database/sources', $choices, 0);
        if (!$picked) {
            return '';
        }

        return $baseDir.DIRECTORY_SEPARATOR.$picked;
    }

    private function normalizeCode(string $code): string
    {
        $code = trim($code);
        $code = rtrim($code, '/');
        return $code;
    }

    private function inferParentFromCode(string $code): string
    {
        if ($code === '' || !str_contains($code, '/')) {
            return '';
        }
        return substr($code, 0, strrpos($code, '/'));
    }

    private function looksLikeFullExport(array $row): bool
    {
        if (!$this->isAssoc($row)) {
            return false;
        }
        return array_key_exists('id', $row) && array_key_exists('parent_id', $row);
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
