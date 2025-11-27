<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferItem;
use Illuminate\Http\Request;

class OfferItemsController extends Controller
{
    public function index(Request $request, Offer $offer)
    {
        return $offer->items()->orderBy('sort_order')->get();
    }

    public function show(OfferItem $item)
    {
        return $item->load('offer:id,number');
    }

    public function store(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'qty' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:16',
            'unit_price' => 'required|numeric',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'line_net' => 'nullable|numeric',
            'line_vat' => 'nullable|numeric',
            'line_gross' => 'nullable|numeric',
            'sort_order' => 'nullable|integer|min:0',
            'meta' => 'nullable|array',
        ]);
        $data['offer_id'] = $offer->id;
        if (!isset($data['sort_order'])) {
            $max = (int) $offer->items()->max('sort_order');
            $data['sort_order'] = $max + 1;
        }
        $item = OfferItem::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, OfferItem $item)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'qty' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:16',
            'unit_price' => 'nullable|numeric',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'line_net' => 'nullable|numeric',
            'line_vat' => 'nullable|numeric',
            'line_gross' => 'nullable|numeric',
            'sort_order' => 'nullable|integer|min:0',
            'meta' => 'nullable|array',
        ]);
        $item->update($data);
        return $item;
    }

    public function destroy(OfferItem $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
