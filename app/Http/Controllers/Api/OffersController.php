<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;

class OffersController extends Controller
{
    public function index(Request $request)
    {
        $q = Offer::query()->with('company:id,name');

        if ($companyId = $request->integer('company_id')) {
            $q->where('company_id', $companyId);
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('number', 'like', "%$search%")
                  ->orWhere('notes', 'like', "%$search%");
            });
        }
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Offer $offer)
    {
        return $offer->load(['company:id,name','items' => function ($q) {
            $q->orderBy('sort_order');
        }]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'number' => 'nullable|string|max:255|unique:offers,number',
            'status' => 'nullable|in:draft,sent,accepted,rejected,expired',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'currency' => 'nullable|string|size:3',
            'commission_percent' => 'nullable|numeric|min:0|max:1',
            'stratton_raise_percent' => 'nullable|numeric|min:0|max:1',
            'subtotal_net' => 'nullable|numeric',
            'total_vat' => 'nullable|numeric',
            'total_gross' => 'nullable|numeric',
            'total_discount' => 'nullable|numeric',
            'meta' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $offer = Offer::create($data);
        return response()->json($offer, 201);
    }

    public function update(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'number' => 'sometimes|nullable|string|max:255|unique:offers,number,'.$offer->id,
            'status' => 'sometimes|in:draft,sent,accepted,rejected,expired',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'currency' => 'nullable|string|size:3',
            'commission_percent' => 'nullable|numeric|min:0|max:1',
            'stratton_raise_percent' => 'nullable|numeric|min:0|max:1',
            'subtotal_net' => 'nullable|numeric',
            'total_vat' => 'nullable|numeric',
            'total_gross' => 'nullable|numeric',
            'total_discount' => 'nullable|numeric',
            'meta' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $offer->update($data);
        return $offer->refresh();
    }

    public function destroy(Offer $offer)
    {
        $offer->delete();
        return response()->noContent();
    }
}
