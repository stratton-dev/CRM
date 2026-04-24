<?php

namespace App\Services\Structure;

use App\Models\OrgCounter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HierarchicalCodeService
{
    public function generate(string $teamGroupPath, ?string $parentCode, ?string $initials = null): string
    {
        $teamPrefix = $this->teamPrefix($teamGroupPath);
        if (!$teamPrefix) {
            throw new RuntimeException('Missing team code for hierarchical code.');
        }

        if ($parentCode) {
            $segmentPrefix = $this->segmentPrefix($initials);
            $scope = $this->scope($teamPrefix, $parentCode, $segmentPrefix);
            $nextNumber = $this->nextNumber($scope);
            $suffix = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            $segment = $segmentPrefix.$suffix;
            return $parentCode.'/'.$segment;
        }

        $rootScope = $this->scope($teamPrefix, null, null);
        $rootNumber = $this->nextNumber($rootScope);
        $rootSuffix = str_pad((string) $rootNumber, 3, '0', STR_PAD_LEFT);
        $rootCode = $teamPrefix.$rootSuffix;

        $segmentPrefix = $this->segmentPrefix($initials);
        $childScope = $this->scope($teamPrefix, $rootCode, $segmentPrefix);
        $childNumber = $this->nextNumber($childScope);
        $childSuffix = str_pad((string) $childNumber, 3, '0', STR_PAD_LEFT);
        $childSegment = $segmentPrefix.$childSuffix;

        return $rootCode.'/'.$childSegment;
    }

    private function scope(string $teamPrefix, ?string $parentCode, ?string $segmentPrefix): string
    {
        $parentPart = $parentCode ?: 'ROOT';
        $segmentPart = $segmentPrefix ?: 'ROOT';
        return 'team='.$teamPrefix.'|parent='.$parentPart.'|segment='.$segmentPart;
    }

    private function teamPrefix(string $teamGroupPath): ?string
    {
        $parts = array_values(array_filter(explode('/', $teamGroupPath)));
        if (!$parts) {
            return null;
        }

        $teamCode = (string) $parts[count($parts) - 1];
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $teamCode) ?: '';
        if ($clean === '') {
            return null;
        }

        return Str::upper(substr($clean, 0, 3));
    }

    private function segmentPrefix(?string $initials): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]/', '', (string) $initials);
        $value = $value ? Str::upper($value) : 'XX';
        return $value;
    }

    private function nextNumber(string $scope): int
    {
        $attempts = 0;

        while ($attempts < 3) {
            $attempts++;

            try {
                return DB::transaction(function () use ($scope) {
                    $counter = OrgCounter::query()
                        ->where('scope', $scope)
                        ->lockForUpdate()
                        ->first();

                    if (!$counter) {
                        OrgCounter::create([
                            'scope' => $scope,
                            'next_number' => 2,
                        ]);

                        return 1;
                    }

                    $current = $counter->next_number;
                    $counter->update(['next_number' => $current + 1]);

                    return $current;
                }, 3);
            } catch (QueryException $exception) {
                if ($attempts >= 3) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to generate hierarchical code.');
    }
}
