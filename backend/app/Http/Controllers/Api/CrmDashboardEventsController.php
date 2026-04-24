<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmDashboardEvent;
use App\Models\User;
use Illuminate\Http\Request;

class CrmDashboardEventsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmDashboardEvent::query();
        if ($userId = $this->resolveUserId($request->input('user_id'))) {
            $q->where('user_id', $userId);
        }
        return $q->orderBy('start_at')->paginate($request->integer('per_page', 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
        ]);
        $userId = $this->resolveUserId($data['user_id'] ?? null);
        $event = CrmDashboardEvent::create([
            'user_id' => $userId,
            'title' => $data['title'],
            'start_at' => $data['start_at'],
        ]);
        return response()->json($event, 201);
    }

    public function update(Request $request, CrmDashboardEvent $crmDashboardEvent)
    {
        $data = $request->validate([
            'user_id' => 'nullable',
            'title' => 'sometimes|required|string|max:255',
            'start_at' => 'sometimes|required|date',
        ]);
        $userId = $this->resolveUserId($data['user_id'] ?? null);
        if (array_key_exists('user_id', $data)) {
            $crmDashboardEvent->user_id = $userId;
        }
        if (array_key_exists('title', $data)) {
            $crmDashboardEvent->title = $data['title'];
        }
        if (array_key_exists('start_at', $data)) {
            $crmDashboardEvent->start_at = $data['start_at'];
        }
        $crmDashboardEvent->save();
        return $crmDashboardEvent;
    }

    public function destroy(CrmDashboardEvent $crmDashboardEvent)
    {
        $crmDashboardEvent->delete();
        return response()->noContent();
    }

    private function resolveUserId($value): ?int
    {
        if (!$value) return null;
        $query = User::query();
        if (is_numeric($value)) {
            $query->where('id', (int) $value)->orWhere('keycloak_id', (string) $value);
        } else {
            $query->where('keycloak_id', (string) $value);
        }
        return $query->first()?->id;
    }
}
