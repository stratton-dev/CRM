<?php

namespace App\Http\Controllers\Api;

use App\Events\Notifications\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CrmClientActivity;
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Structure\StructureService;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    public function index(Request $request, TokenContext $context, StructureService $structure)
    {
        $authUser = $request->user();
        $q = Client::query()->with(['crmProfile', 'crmProfile.owner:id,supabase_id,name']);

        // LEADOWIEC: widzi tylko własnych klientów
        if ($authUser && $authUser->role_cached === 'LEADOWIEC') {
            $q->where('added_by_user_id', $authUser->id);
        } else {
            $role = $context->primaryRole();
            if ($role !== 'ADMIN') {
                // Opiekun może filtrować leady od swoich leadowców
                if ($request->boolean('from_my_leadowcy') && in_array($role, ['SALES', 'MANAGER', 'DIRECTOR'], true)) {
                    $leadowcyIds = User::where('leadowiec_opiekun_id', $authUser->id)->pluck('id');
                    $q->whereIn('added_by_user_id', $leadowcyIds);
                } else {
                    $users = $structure->listUsers($context);
                    $userIds = collect($users)->pluck('id')->unique()->values()->all();

                    if (empty($userIds)) {
                        $q->whereRaw('1 = 0');
                    } else {
                        // Find LEADOWIECs assigned to any user in the structure
                        $leadowcyIds = User::where('role_cached', 'LEADOWIEC')
                            ->whereIn('leadowiec_opiekun_id', $userIds)
                            ->pluck('id');

                        $q->where(function ($query) use ($userIds, $leadowcyIds) {
                            $query->whereHas('crmProfile', function ($p) use ($userIds) {
                                $p->whereIn('owner_user_id', $userIds);
                            })->orWhereHas('meetings', function ($m) use ($userIds) {
                                $m->whereIn('user_id', $userIds);
                            });
                            if ($leadowcyIds->isNotEmpty()) {
                                $query->orWhereIn('added_by_user_id', $leadowcyIds);
                            }
                        });
                    }
                }
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

    public function show(Request $request, Client $client)
    {
        $authUser = $request->user();
        if ($authUser && $authUser->role_cached === 'LEADOWIEC') {
            abort_if($client->added_by_user_id !== $authUser->id, 403, 'Brak dostępu do tego klienta.');
        }
        return $client->load(['crmProfile', 'crmProfile.owner:id,supabase_id', 'notes.author:id,name'])->loadCount(['contacts', 'meetings', 'payrolls']);
    }

    public function store(Request $request, TokenContext $context)
    {
        $authUser = $request->user();
        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'nip' => 'nullable|string|max:255|unique:companies,nip',
            'name' => 'required|string|max:255',
            'regon' => 'nullable|string|max:20',
            'krs' => 'nullable|string|max:20',
            'address_json' => 'nullable|array',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:12',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'accountant_name' => 'nullable|string|max:255',
            'accountant_email' => 'nullable|email|max:255',
            'industry' => 'nullable|string|max:255',
            'vat_type' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:0',
            'benefits_enabled' => 'nullable|boolean',
            // CRM profile fields collected by the inline create form
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:64',
            'contact_email' => 'nullable|email|max:255',
            'source' => 'nullable|string|max:255',
        ]);

        // Pull CRM profile fields out of the company payload before persisting.
        $profileExtras = collect($data)
            ->only(['contact_name', 'contact_phone', 'contact_email', 'source'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $companyData = collect($data)
            ->except(['contact_name', 'contact_phone', 'contact_email', 'source'])
            ->all();

        // LEADOWIEC: auto-set added_by_user_id, force status=lead
        if ($authUser && $authUser->role_cached === 'LEADOWIEC') {
            $companyData['added_by_user_id'] = $authUser->id;
        }

        $client = Client::create($companyData);

        // Auto-assign owner and create CRM profile
        $userId = $authUser?->id ?? $this->resolveUserId($context->actorSupabaseId());
        if ($userId) {
            $client->crmProfile()->create(array_merge([
                'owner_user_id' => $userId,
                'status' => 'NEW',
            ], $profileExtras));

            CrmClientActivity::create([
                'client_id' => $client->id,
                'user_id' => $userId,
                'type' => 'NOTE',
                'description' => 'Utworzono rekord klienta',
                'occurred_at' => now(),
            ]);
        }

        // LEADOWIEC: notify opiekun
        if ($authUser && $authUser->role_cached === 'LEADOWIEC' && $authUser->leadowiec_opiekun_id) {
            $this->notifyOpiekun($authUser, $client);
        }

        return response()->json($client->load(['crmProfile', 'crmProfile.owner:id,supabase_id,name']), 201);
    }

    private function notifyOpiekun(User $leadowiec, Client $company): void
    {
        $opiekun = $leadowiec->opiekun;
        if (!$opiekun) {
            \Illuminate\Support\Facades\Log::warning('LEADOWIEC notifyOpiekun: opiekun not found', ['leadowiec_id' => $leadowiec->id]);
            return;
        }

        $notification = Notification::create([
            'user_id'   => $opiekun->id,
            'sender_id' => $leadowiec->id,
            'type'      => 'new_lead_from_leadowiec',
            'title'     => "Nowy lead od {$leadowiec->name}",
            'body'      => "Dodano klienta: {$company->name}",
        ]);

        event(new NotificationCreated($notification));
    }

    public function update(Request $request, Client $client, TokenContext $context)
    {
        $authUser = $request->user();

        // LEADOWIEC: może edytować tylko swoje klienty, ograniczone pola
        if ($authUser && $authUser->role_cached === 'LEADOWIEC') {
            abort_if($client->added_by_user_id !== $authUser->id, 403, 'Brak dostępu do tego klienta.');

            $data = $request->validate([
                'name'         => 'sometimes|required|string|max:255',
                'email'        => 'nullable|email|max:255',
                'phone'        => 'nullable|string|max:64',
                'address_line1' => 'nullable|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'postal_code'  => 'nullable|string|max:12',
                'city'         => 'nullable|string|max:255',
                'website'      => 'nullable|string|max:255',
                'notes'        => 'nullable|string',
                'nip'          => 'nullable|string|max:255|unique:companies,nip,'.$client->id,
                'contact_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:64',
                'contact_email' => 'nullable|email|max:255',
                'contact_position' => 'nullable|string|max:255',
                'is_decision_maker' => 'nullable|boolean',
            ]);
            $client->update($data);

            $profileData = array_intersect_key($data, array_flip([
                'contact_name', 'contact_phone', 'contact_email', 'contact_position', 'is_decision_maker',
            ]));
            if (!empty($profileData) && $client->crmProfile) {
                $client->crmProfile->update($profileData);
            }

            return $client->load('crmProfile');
        }

        $data = $request->validate([
            'organization_id' => 'sometimes|nullable|exists:organizations,id',
            'nip' => 'sometimes|required|string|max:255|unique:companies,nip,'.$client->id,
            'name' => 'sometimes|required|string|max:255',
            'regon' => 'nullable|string|max:20',
            'krs' => 'nullable|string|max:20',
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

        $userId = $this->resolveUserId($context->actorSupabaseId());

        // Update profile if any profile fields are present
        $profileData = array_intersect_key($data, array_flip([
            'contact_name', 'contact_phone', 'contact_email', 'contact_position',
            'is_decision_maker', 'source', 'company_size', 'industry'
        ]));

        if (!empty($profileData)) {
            $existingProfile = $client->crmProfile;
            if ($existingProfile) {
                $existingProfile->update($profileData);
            } else {
                $client->crmProfile()->create(array_merge(
                    $profileData,
                    $userId ? ['owner_user_id' => $userId] : []
                ));
            }
        }

        // Auto-register activity
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
