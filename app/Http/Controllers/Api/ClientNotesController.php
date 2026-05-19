<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNotesController extends Controller
{
    public function index(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client);

        return response()->json(
            $client->notes()->with('author:id,name')->latest()->get()
        );
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client);

        $note = $client->notes()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validate(['content' => 'required|string|max:5000'])['content'],
        ]);

        return response()->json($note->load('author:id,name'), 201);
    }

    public function update(Request $request, Client $client, ClientNote $note): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client, $note);
        abort_if($note->user_id !== $request->user()->id, 403, 'Możesz edytować tylko swoje notatki.');

        $note->update([
            'content' => $request->validate(['content' => 'required|string|max:5000'])['content'],
        ]);

        return response()->json($note);
    }

    public function destroy(Request $request, Client $client, ClientNote $note): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client, $note);
        abort_if($note->user_id !== $request->user()->id, 403, 'Możesz usuwać tylko swoje notatki.');

        $note->delete();
        return response()->json(null, 204);
    }

    private function authorizeClientAccess($user, Client $client, ?\App\Models\ClientNote $note = null): void
    {
        if ($note !== null) {
            abort_if($note->company_id !== $client->id, 404);
        }

        if ($user && $user->role_cached === 'LEADOWIEC') {
            abort_if($client->added_by_user_id !== $user->id, 403, 'Brak dostępu do tego klienta.');
        }
    }
}
