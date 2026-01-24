<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\KeycloakSyncJob;
use App\Models\OrgCounter;
use App\Models\User;
use App\Services\Structure\HierarchicalCodeService;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
