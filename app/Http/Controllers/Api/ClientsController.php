<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CrmClientActivity;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Structure\StructureService;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    public function index(Request $request, TokenContext $context, StructureService $structure)
    {
        $q = Client::query()->with(['crmProfile', 'crmProfile.owner:id,keycloak_id']);
        /*
        $role = $context->primaryRole();
        if ($role !== 'ADMIN') {
            $userIds = $structure->listUsers($context)->pluck('id')->all();
            if (!$userIds) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereHas('meetings', function ($m) use ($userIds) {
                    $m->whereIn('user_id', $userIds);
                });
            }
        }
        */
        if ($organizationId = $request->integer('organization_id')) {
            $q->where('organization_id', $organizationId);
        }
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%$search%")
                  ->orWhere('nip', 'like', "%$search%");
            });
        }
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function checkNip(Request $request)
    {
        $data = $request->validate([
            'nip' => 'required|string|max:255',
        ]);
        $nip = preg_replace('/\D/', '', $data['nip']);
        if ($nip === '') {
            return response()->json(['reserved' => false]);
        }

        $client = Client::query()->where('nip', $nip)->first();
        if (!$client) {
            return response()->json(['reserved' => false]);
        }

        $hasConsents = $client->consents()
            ->whereNotNull('accepted_at')
            ->whereNull('denied_at')
            ->exists();
        if (!$hasConsents) {
            return response()->json(['reserved' => false, 'client_id' => $client->id]);
        }

        $meeting = $client->meetings()
            ->where('status', 'open')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->with('user')
            ->orderByDesc('valid_until')
            ->first();
        if (!$meeting) {
            return response()->json(['reserved' => false, 'client_id' => $client->id]);
        }

        return response()->json([
            'reserved' => true,
            'client_id' => $client->id,
            'meeting_id' => $meeting->id,
            'valid_until' => optional($meeting->valid_until)->toDateString(),
            'owner_name' => optional($meeting->user)->name,
        ]);
    }

    public function show(Client $client)
    {
        return $client->load(['crmProfile', 'crmProfile.owner:id,keycloak_id'])->loadCount(['contacts', 'meetings', 'payrolls']);
    }

    public function store(Request $request, TokenContext $context)
    {
        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'nip' => 'required|string|max:255|unique:companies,nip',
            'name' => 'required|string|max:255',
            'address_json' => 'nullable|array',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:12',
            'city' => 'required|string|max:255',
            'country' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'industry' => 'nullable|string|max:255',
            'vat_type' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:0',
            'benefits_enabled' => 'nullable|boolean',
        ]);
        $client = Client::create($data);

        // Auto-register activity
        $userId = $this->resolveUserId($context->actorKeycloakId());
        if ($userId) {
            CrmClientActivity::create([
                'client_id' => $client->id,
                'user_id' => $userId,
                'type' => 'NOTE',
                'description' => 'Utworzono rekord klienta',
                'occurred_at' => now(),
            ]);
        }

        return response()->json($client, 201);
    }

    public function update(Request $request, Client $client, TokenContext $context)
    {
        $data = $request->validate([
            'organization_id' => 'sometimes|nullable|exists:organizations,id',
            'nip' => 'sometimes|required|string|max:255|unique:companies,nip,'.$client->id,
            'name' => 'sometimes|required|string|max:255',
            'address_json' => 'nullable|array',
            'address_line1' => 'sometimes|required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postal_code' => 'sometimes|required|string|max:12',
            'city' => 'sometimes|required|string|max:255',
            'country' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'industry' => 'nullable|string|max:255',
            'vat_type' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:0',
            'benefits_enabled' => 'nullable|boolean',
            // Prospecting fields for profile
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:64',
            'contact_email' => 'nullable|email|max:255',
            'contact_position' => 'nullable|string|max:255',
            'is_decision_maker' => 'nullable|boolean',
            'source' => 'nullable|string|max:255',
            'company_size' => 'nullable|string|max:50',
        ]);
        $client->update($data);

        // Update profile if any profile fields are present
        $profileData = array_intersect_key($data, array_flip([
            'contact_name', 'contact_phone', 'contact_email', 'contact_position',
            'is_decision_maker', 'source', 'company_size', 'industry'
        ]));

        if (!empty($profileData)) {
            $client->crmProfile()->updateOrCreate([], $profileData);
        }

        // Auto-register activity
        $userId = $this->resolveUserId($context->actorKeycloakId());
        if ($userId) {
            CrmClientActivity::create([
                'client_id' => $client->id,
                'user_id' => $userId,
                'type' => 'NOTE',
                'description' => 'Zaktualizowano informacje o kliencie',
                'occurred_at' => now(),
            ]);
        }

        return $client->load('crmProfile');
    }

    public function destroy(Client $client)
    {
        $client->delete();
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
