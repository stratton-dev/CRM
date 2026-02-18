<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CandidatesController extends Controller
{
    public function index(Request $request)
    {
        $query = Candidate::with('documents');

        // Optional: filter by manager if needed
        // if (!$request->user()->hasRole('ADMIN')) {
        //     $query->where('manager_id', $request->user()->id);
        // }

        return $query->latest()->paginate($request->input('per_page', 25));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['person', 'sole_proprietorship', 'company'])],
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'company_name' => 'nullable|string',
            'nip' => 'nullable|string',
            'regon' => 'nullable|string',
            'krs' => 'nullable|string',
            'pesel' => 'nullable|string|size:11',
            'email' => 'required|email',
            'phone' => 'required|string',
            'address_json' => 'nullable|array',
            'documents' => 'nullable|array',
            'documents.*' => 'string', // Document types to create
        ]);

        $candidate = DB::transaction(function () use ($validated, $request) {
            $candidate = Candidate::create([
                ...$validated,
                'manager_id' => $request->user()->id ?? null, // Assuming auth
                'status' => 'new'
            ]);

            if (!empty($validated['documents'])) {
                foreach ($validated['documents'] as $docType) {
                    CandidateDocument::create([
                        'candidate_id' => $candidate->id,
                        'type' => $docType,
                        'status' => 'pending'
                    ]);
                }
            }

            return $candidate;
        });

        // Trigger Autenti process here if needed (could be an Event)

        return response()->json($candidate->load('documents'), 201);
    }

    public function show(Candidate $candidate)
    {
        return $candidate->load('documents');
    }

    public function update(Request $request, Candidate $candidate)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'company_name' => 'nullable|string',
            'nip' => 'nullable|string',
            'regon' => 'nullable|string',
            'krs' => 'nullable|string',
            'pesel' => 'nullable|string|size:11',
            'email' => 'email',
            'phone' => 'string',
            'address_json' => 'nullable|array',
        ]);

        $candidate->update($validated);
        return $candidate;
    }

    public function destroy(Candidate $candidate)
    {
        $candidate->delete();
        return response()->noContent();
    }
}
