<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientConsent;
use Illuminate\Http\Request;

class ClientConsentsController extends Controller
{
    public function index(Client $client)
    {
        return $client->consents()->with('consent')->latest('accepted_at')->get();
    }

    public function show(ClientConsent $clientConsent)
    {
        return $clientConsent->load(['client:id,name', 'consent']);
    }

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'consent_id' => 'required|exists:consents,id',
            'accepted_at' => 'required|date',
            'denied_at' => 'nullable|date',
            'source' => 'required|string|max:255',
        ]);
        $data['client_id'] = $client->id;
        $clientConsent = ClientConsent::create($data);
        return response()->json($clientConsent, 201);
    }

    public function update(Request $request, ClientConsent $clientConsent)
    {
        $data = $request->validate([
            'consent_id' => 'sometimes|required|exists:consents,id',
            'accepted_at' => 'sometimes|required|date',
            'denied_at' => 'nullable|date',
            'source' => 'sometimes|required|string|max:255',
        ]);
        $clientConsent->update($data);
        return $clientConsent;
    }

    public function destroy(ClientConsent $clientConsent)
    {
        $clientConsent->delete();
        return response()->noContent();
    }
}
