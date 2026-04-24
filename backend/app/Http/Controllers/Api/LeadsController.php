<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Company;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadsController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // By default, show active leads (not converted/rejected) unless specified
             $query->whereNotIn('status', ['converted']);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:20', // Start with basic validation, unique check might duplicate across tables
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if NIP exists in Companies (already a client)
        if (Company::where('nip', $request->nip)->exists()) {
             return response()->json(['message' => 'Firma z tym numerem NIP już istnieje w bazie klientów.'], 409);
        }

        // Check if NIP exists in Leads (already a lead)
        if (Lead::where('nip', $request->nip)->where('status', '!=', 'rejected')->exists()) {
            return response()->json(['message' => 'Lead z tym numerem NIP już istnieje.'], 409);
        }

        $lead = Lead::create([
            'name' => $request->name,
            'nip' => $request->nip,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'email' => $request->email,
            'notes' => $request->notes,
            'user_id' => $request->user()->id, // Assign to current user
            'status' => 'new',
            'source' => $request->source ?? 'manual',
        ]);

        return response()->json($lead, 201);
    }

    public function show(Lead $lead)
    {
        return response()->json($lead);
    }

    public function update(Request $request, Lead $lead)
    {
        $lead->update($request->only([
            'name', 'nip', 'contact_person', 'phone', 'email', 'notes', 'status'
        ]));

        if ($request->has('note_content')) {
             // Append note if provided separately
             // In a real system, we'd have a separate notes table.
             // For now, we update the simple text notes or handle separately.
             // Requirement says: "System notatek... widoczny historycznie".
             // We'll address Note System separately.
        }

        return response()->json($lead);
    }

    // Specifically for qualifying a lead (Phone call made)
    public function qualify(Request $request, Lead $lead)
    {
        $request->validate([
            'note' => 'required|string|min:5'
        ]);

        // Logic: Add note, update status
        // TODO: Integrate with Note System

        $lead->status = 'qualified';
        $lead->last_contact_at = now();
        $lead->save();

        return response()->json(['message' => 'Lead zakwalifikowany', 'lead' => $lead]);
    }

    public function convert(Request $request, Lead $lead)
    {
        if ($lead->status === 'converted') {
            return response()->json(['message' => 'Lead już przekonwertowany'], 400);
        }

        // Transaction to ensure atomicity
        DB::beginTransaction();
        try {
            // 1. Create Company
            $company = Company::create([
                'name' => $lead->name,
                'nip' => $lead->nip,
                'address_line1' => 'TBD', // Placeholder, required by Company model?
                'postal_code' => '00-000',
                'city' => 'Unknown',
                'email' => $lead->email,
                'phone' => $lead->phone,
                'notes' => $lead->notes,
            ]);

            // 2. Create Meeting (Stage 2)
            $meeting = Meeting::create([
                'client_id' => $company->id,
                'user_id' => $request->user()->id,
                'status' => 'open',
                'valid_until' => now()->addDays(30), // Default validity
            ]);

            // 3. Update Lead status
            $lead->status = 'converted';
            $lead->save();

            // 4. Copy specific lead data if needed (e.g. contact person to ClientContact)
            if ($lead->contact_person) {
                $company->contacts()->create([
                    'name' => $lead->contact_person,
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                    'is_primary' => true
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Lead przekonwertowany do Spotkania',
                'company_id' => $company->id,
                'meeting_id' => $meeting->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Błąd konwersji: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();
        return response()->json(['message' => 'Lead usunięty']);
    }
}
