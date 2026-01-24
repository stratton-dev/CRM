<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutentiDocument;
use App\Services\Autenti\AutentiOnboardingService;
use Illuminate\Http\Request;

class AutentiDocumentsController extends Controller
{
    public function index(Request $request)
    {
        $query = AutentiDocument::query()->orderByDesc('sent_at');

        if ($userKeycloakId = $request->string('user_keycloak_id')->toString()) {
            $query->where('user_keycloak_id', $userKeycloakId);
        }

        return $query->get();
    }

    public function show(AutentiDocument $autentiDocument)
    {
        return $autentiDocument;
    }

    public function sync(AutentiDocument $autentiDocument, AutentiOnboardingService $service)
    {
        return $service->sync($autentiDocument);
    }
}
