<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmSavedOffer;
use App\Services\Structure\StructureService;
use App\Services\Auth\TokenContext;
use Illuminate\Http\Request;

class CrmSavedOffersController extends Controller
{
    public function index(Request $request, TokenContext $context, StructureService $structure)
    {
        $q = CrmSavedOffer::query();

        $authUser = $request->user();
        $role = $context->primaryRole();

        // LEADOWIEC (read-only): zapisane oferty klientów, których sam zgłosił.
        if ($authUser && $authUser->role_cached === 'LEADOWIEC') {
            $q->whereHas('client', function ($c) use ($authUser) {
                $c->where('added_by_user_id', $authUser->id);
            });
        } elseif ($role !== 'ADMIN') {
            $users = $structure->listUsers($context);
            $userIds = collect($users)->pluck('id')->unique()->values()->all();

            if (empty($userIds)) {
                $q->whereRaw('1 = 0');
            } else {
                // Filter offers for clients that are visible to the user
                $q->whereHas('client', function ($cq) use ($userIds) {
                    $cq->where(function ($w) use ($userIds) {
                        $w->whereHas('crmProfile', function ($p) use ($userIds) {
                             $p->whereIn('owner_user_id', $userIds);
                        })->orWhereHas('meetings', function ($m) use ($userIds) {
                             $m->whereIn('user_id', $userIds);
                        });
                    });
                });
            }
        }

        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        return $q->latest()->paginate($request->integer('per_page', 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'employees_uop' => 'nullable|integer|min:0',
            'avg_wage_uop' => 'nullable|numeric|min:0',
            'employees_uz' => 'nullable|integer|min:0',
            'estimated_savings' => 'nullable|numeric|min:0',
        ]);
        $offer = CrmSavedOffer::create($data);
        return response()->json($offer, 201);
    }

    public function update(Request $request, CrmSavedOffer $crmSavedOffer)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'employees_uop' => 'nullable|integer|min:0',
            'avg_wage_uop' => 'nullable|numeric|min:0',
            'employees_uz' => 'nullable|integer|min:0',
            'estimated_savings' => 'nullable|numeric|min:0',
        ]);
        $crmSavedOffer->update($data);
        return $crmSavedOffer;
    }

    public function destroy(CrmSavedOffer $crmSavedOffer)
    {
        $crmSavedOffer->delete();
        return response()->noContent();
    }
}
