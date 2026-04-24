<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmClientProfile;
use App\Models\CrmClientActivity;
use App\Models\User;
use App\Services\Structure\StructureService;
use App\Services\Auth\TokenContext;
use Illuminate\Http\Request;

class CrmClientProfilesController extends Controller
{
    public function index(Request $request, TokenContext $context, StructureService $structure)
    {
        $q = CrmClientProfile::query();

        $role = $context->primaryRole();
        if ($role !== 'ADMIN') {
            $users = $structure->listUsers($context);
            $userIds = collect($users)->pluck('id')->unique()->values()->all();

            if (empty($userIds)) {
                $q->whereRaw('1 = 0');
            } else {
                // Filter profiles owned by users in the subtree
                // We could also join meetings here, but usually Profile is directly owned.
                $q->whereIn('owner_user_id', $userIds);
            }
        }

        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        return $q->paginate($request->integer('per_page', 100));
    }

    public function show(CrmClientProfile $crmClientProfile)
    {
        return $crmClientProfile;
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $profile = CrmClientProfile::create($data);
        return response()->json($profile, 201);
    }

    public function update(Request $request, CrmClientProfile $crmClientProfile, TokenContext $context)
    {
        $oldStatus = $crmClientProfile->status;
        $data = $this->validatedData($request, true);
        $crmClientProfile->update($data);

        // Auto-register activity if status changed or general update
        $userId = $this->resolveUserId($context->actorKeycloakId());
        if ($userId) {
            $description = 'Zaktualizowano profil klienta';
            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $description = "Zmieniono status na: " . $data['status'];
            }

            CrmClientActivity::create([
                'client_id' => $crmClientProfile->client_id,
                'user_id' => $userId,
                'type' => 'NOTE',
                'description' => $description,
                'occurred_at' => now(),
            ]);
        }

        return $crmClientProfile;
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'client_id' => ($partial ? 'sometimes|' : '') . 'required|exists:companies,id',
            'owner_user_id' => 'nullable',
            'status' => 'nullable|in:NEW,IN_TALKS,OFFER_PREPARING,OFFER_GENERATED,CALCULATION_SENT,SPECIAL_OFFER,RESIGNED,SIGNED,TERMINATED',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:64',
            'contact_email' => 'nullable|email|max:255',
            'employees_total' => 'nullable|integer|min:0',
            'employees_uop' => 'nullable|integer|min:0',
            'employees_uz' => 'nullable|integer|min:0',
            'avg_wage_uop' => 'nullable|numeric|min:0',
            'avg_wage_uz' => 'nullable|numeric|min:0',
            'service_fee_percent' => 'nullable|numeric|min:0',
            'offer_sent_date' => 'nullable|date',
            'contract_signed_date' => 'nullable|date',
            'reservation_end_date' => 'nullable|date',
            'analysis_json' => 'nullable|array',
        ];
        $data = $request->validate($rules);
        if (!empty($data['owner_user_id'])) {
            $ownerId = $this->resolveUserId($data['owner_user_id']);
            $data['owner_user_id'] = $ownerId;
        }
        return $data;
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
