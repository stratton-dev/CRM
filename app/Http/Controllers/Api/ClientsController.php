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
        $q = Client::query()->with(['crmProfile', 'crmProfile.owner:id,supabase_id,name']);

        $role = $context->primaryRole();
        if ($role !== 'ADMIN') {
            $users = $structure->listUsers($context);
            $userIds = collect($users)->pluck('id')->unique()->values()->all();

            if (empty($userIds)) {
                 // If no users found in scope (should not happen for valid users), return empty
                $q->whereRaw('1 = 0');
            } else {
                // Filter clients owned by users in the subtree OR having meetings with users in the subtree
                $q->where(function ($query) use ($userIds) {
                    $query->whereHas('crmProfile', function ($p) use ($userIds) {
                        $p->whereIn('owner_user_id', $userIds);
                    })->orWhereHas('meetings', function ($m) use ($userIds) {
                        $m->whereIn('user_id', $userIds);
                    });
                });
            }
        }

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

        $client = Client::where('nip', $nip)->first();

        // 1. Jeśli klienta nie ma w ogóle w bazie -> NIP wolny
        if (!$client) {
            return response()->json(['reserved' => false]);
        }

        // 2. Pobieramy ID zalogowanego usera
        $currentUser = $request->user();

        // 3. Sprawdzamy czy istnieje aktywna rezerwacja (spotkanie w statusie 'open')
        // WAŻNE: Dodajemy warunek, że rezerwacja blokuje TYLKO jeśli należy do KOGOŚ INNEGO.
        // Jeśli należy do mnie ($currentUser->id), to mogę działać dalej.
        $meetingQuery = $client->meetings()
            ->where('status', 'open')
            ->where('valid_until', '>=', now())
            ->orderByDesc('created_at');

        if ($currentUser) {
            $meetingQuery->where('user_id', '!=', $currentUser->id);
        }

        $activeReservation = $meetingQuery->with('user')->first();

        if (!$activeReservation) {
            // Brak aktywnej rezerwacji innej osoby -> Wolne (nawet jeśli mam własną, to dla mnie jest wolne)
            return response()->json(['reserved' => false, 'client_id' => $client->id]);
        }

        // Jest aktywna rezerwacja kogoś innego -> ZABLOKOWANE
        return response()->json([
            'reserved' => true,
            'client_id' => $client->id,
            'meeting_id' => $activeReservation->id,
            'valid_until' => optional($activeReservation->valid_until)->toDateString(),
            'owner_name' => optional($activeReservation->user)->name,
        ]);
    }

    public function show(Client $client)
    {
        return $client->load(['crmProfile', 'crmProfile.owner:id,supabase_id'])->loadCount(['contacts', 'meetings', 'payrolls']);
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
        $userId = $this->resolveUserId($context->actorSupabaseId());
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
        $userId = $this->resolveUserId($context->actorSupabaseId());
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
            $query->where('id', (int) $value)->orWhere('supabase_id', (string) $value);
        } else {
            $query->where('supabase_id', (string) $value);
        }
        return $query->first()?->id;
    }
}
