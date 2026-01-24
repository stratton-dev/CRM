<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmSavedOffer;
use Illuminate\Http\Request;

class CrmSavedOffersController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmSavedOffer::query();
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
