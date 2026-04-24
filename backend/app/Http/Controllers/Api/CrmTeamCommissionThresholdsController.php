<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmTeamCommissionThreshold;
use Illuminate\Http\Request;

class CrmTeamCommissionThresholdsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmTeamCommissionThreshold::query();
        if ($path = $request->string('team_group_path')->toString()) {
            $q->where('team_group_path', $path);
        }
        return $q->orderBy('team_group_path')->get();
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'team_group_path' => 'required|string|max:255',
            'renewal_commission_rate' => 'nullable|numeric|min:0|max:1',
            'override_commission_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        $record = CrmTeamCommissionThreshold::updateOrCreate(
            ['team_group_path' => $data['team_group_path']],
            $data
        );

        return $record;
    }
}
