<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmOfferPdf;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmOfferPdfsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmOfferPdf::query()
            ->with(['user:id,name,email']);

        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }

        // Don't ship the heavy base64 blob in list responses — show only
        // metadata and let the client fetch the full row when downloading.
        $rows = $q->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50), [
                'id', 'client_id', 'user_id', 'calculation_id',
                'name', 'mime_type', 'size_bytes', 'valid_until',
                'created_at', 'updated_at',
            ]);

        return response()->json($rows);
    }

    public function show(CrmOfferPdf $crmOfferPdf): JsonResponse
    {
        // Returns the full row including pdf_base64 for download.
        return response()->json($crmOfferPdf->load('user:id,name,email'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => 'required|integer|exists:companies,id',
            'user_id' => 'nullable',
            'calculation_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'mime_type' => 'nullable|string|max:128',
            'valid_until' => 'nullable|date',
            'pdf_base64' => 'required|string', // raw base64 (no data: prefix)
        ]);

        $userId = $this->resolveUserId($data['user_id'] ?? null) ?? $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'User not found.'], 422);
        }

        // Strip a possible "data:application/pdf;base64," prefix.
        $base64 = $data['pdf_base64'];
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $sizeBytes = (int) floor(strlen($base64) * 3 / 4);

        $row = CrmOfferPdf::create([
            'client_id' => $data['client_id'],
            'user_id' => $userId,
            'calculation_id' => $data['calculation_id'] ?? null,
            'name' => $data['name'],
            'mime_type' => $data['mime_type'] ?? 'application/pdf',
            'size_bytes' => $sizeBytes,
            'valid_until' => $data['valid_until'] ?? null,
            'pdf_base64' => $base64,
        ]);

        return response()->json(
            $row->only([
                'id', 'client_id', 'user_id', 'calculation_id',
                'name', 'mime_type', 'size_bytes', 'valid_until',
                'created_at', 'updated_at',
            ]),
            201
        );
    }

    public function destroy(CrmOfferPdf $crmOfferPdf): JsonResponse
    {
        $crmOfferPdf->delete();
        return response()->json(null, 204);
    }

    private function resolveUserId($value): ?int
    {
        if (!$value) {
            return null;
        }
        $query = User::query();
        if (is_numeric($value)) {
            $query->where('id', (int) $value)->orWhere('supabase_id', (string) $value);
        } else {
            $query->where('supabase_id', (string) $value);
        }
        $u = $query->first();
        return $u?->id;
    }
}
