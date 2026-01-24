<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use Illuminate\Http\Request;

class ConsentsController extends Controller
{
    public function index(Request $request)
    {
        $q = Consent::query();
        return $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
    }

    public function show(Consent $consent)
    {
        return $consent;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255|unique:consents,code',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'required' => 'nullable|boolean',
        ]);
        $consent = Consent::create($data);
        return response()->json($consent, 201);
    }

    public function update(Request $request, Consent $consent)
    {
        $data = $request->validate([
            'code' => 'sometimes|required|string|max:255|unique:consents,code,'.$consent->id,
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:255',
            'required' => 'nullable|boolean',
        ]);
        $consent->update($data);
        return $consent;
    }

    public function destroy(Consent $consent)
    {
        $consent->delete();
        return response()->noContent();
    }
}
