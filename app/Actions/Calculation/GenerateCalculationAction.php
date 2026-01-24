<?php

namespace App\Actions\Calculation;

use App\Models\Calculation;
use App\Models\Meeting;
use App\Services\Meeting\MeetingRulesService;

class GenerateCalculationAction
{
    public function __construct(private MeetingRulesService $meetingRulesService)
    {
    }

    public function execute(Meeting $meeting, int $employeeCount, int $savingsAmount): Calculation
    {
        $this->meetingRulesService->ensureActive($meeting);
        $this->meetingRulesService->ensureNotExpired($meeting);

        return Calculation::create([
            'meeting_id' => $meeting->id,
            'employee_count' => $employeeCount,
            'savings_amount' => $savingsAmount,
            'valid_until' => now()->addDays(14),
        ]);
    }
}
