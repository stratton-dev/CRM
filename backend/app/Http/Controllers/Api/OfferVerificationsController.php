<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferVerification;
use Illuminate\Http\Request;

class OfferVerificationsController extends Controller
{
    public function index(Offer $offer)
    {
        return $offer->verifications()->latest('id')->get();
    }

    public function show(OfferVerification $verification)
    {
        return $verification->load('offer:id,number,token');
    }

    public function store(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'type' => 'required|in:email,sms',
            'code' => 'required|string|max:255',
            'verified_at' => 'nullable|date',
        ]);
        $data['offer_id'] = $offer->id;
        $verification = OfferVerification::create($data);
        return response()->json($verification, 201);
    }

    public function update(Request $request, OfferVerification $verification)
    {
        $data = $request->validate([
            'type' => 'sometimes|in:email,sms',
            'code' => 'sometimes|string|max:255',
            'verified_at' => 'nullable|date',
        ]);
        $verification->update($data);
        return $verification;
    }

    public function destroy(OfferVerification $verification)
    {
        $verification->delete();
        return response()->noContent();
    }
}
