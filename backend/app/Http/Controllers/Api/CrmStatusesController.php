<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmStatus;
use Illuminate\Http\Request;

class CrmStatusesController extends Controller
{
    public function index()
    {
        return CrmStatus::query()
            ->with('events:id,key,label')
            ->orderBy('sort_order')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string|max:50|unique:crm_statuses,key',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'integer|exists:crm_events,id',
        ]);

        $status = CrmStatus::create($data);
        if (isset($data['event_ids'])) {
            $status->events()->sync($data['event_ids']);
        }

        return $status->load('events:id,key,label');
    }

    public function update(Request $request, CrmStatus $crmStatus)
    {
        $data = $request->validate([
            'key' => 'sometimes|required|string|max:50|unique:crm_statuses,key,' . $crmStatus->id,
            'label' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'integer|exists:crm_events,id',
        ]);

        $crmStatus->update($data);
        if (array_key_exists('event_ids', $data)) {
            $crmStatus->events()->sync($data['event_ids'] ?? []);
        }

        return $crmStatus->load('events:id,key,label');
    }

    public function destroy(CrmStatus $crmStatus)
    {
        $crmStatus->delete();
        return response()->noContent();
    }
}
