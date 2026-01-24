<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmEvent;
use Illuminate\Http\Request;

class CrmEventsController extends Controller
{
    public function index()
    {
        return CrmEvent::query()->orderBy('label')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string|max:100|unique:crm_events,key',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);

        return CrmEvent::create($data);
    }

    public function update(Request $request, CrmEvent $crmEvent)
    {
        $data = $request->validate([
            'key' => 'sometimes|required|string|max:100|unique:crm_events,key,' . $crmEvent->id,
            'label' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);

        $crmEvent->update($data);
        return $crmEvent;
    }

    public function destroy(CrmEvent $crmEvent)
    {
        $crmEvent->delete();
        return response()->noContent();
    }
}
