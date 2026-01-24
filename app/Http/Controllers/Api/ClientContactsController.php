<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\Request;

class ClientContactsController extends Controller
{
    public function index(Client $client)
    {
        return $client->contacts()->latest()->get();
    }

    public function show(ClientContact $contact)
    {
        return $contact->load('client:id,name');
    }

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'is_decision_maker' => 'nullable|boolean',
        ]);
        $data['client_id'] = $client->id;
        $contact = ClientContact::create($data);
        return response()->json($contact, 201);
    }

    public function update(Request $request, ClientContact $contact)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'is_decision_maker' => 'nullable|boolean',
        ]);
        $contact->update($data);
        return $contact;
    }

    public function destroy(ClientContact $contact)
    {
        $contact->delete();
        return response()->noContent();
    }
}
