<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollSpreadsheet;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PayrollSpreadsheetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PayrollSpreadsheet::with(['client.crmProfile.owner', 'user:id,name']);

        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->latest()->paginate($request->input('per_page', 15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:companies,id', // companies table
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $clientId = $request->input('client_id');
        $originalName = $file->getClientOriginalName();

        // Store file
        $path = $file->storeAs(
            "payrolls/{$clientId}",
            time() . '_' . $originalName
        );

        $spreadsheet = PayrollSpreadsheet::create([
            'client_id' => $clientId,
            'user_id' => $request->user()->id,
            'original_filename' => $originalName,
            'storage_path' => $path,
            'status' => 'UPLOADED',
        ]);

        return response()->json($spreadsheet->load(['client:id,name', 'user:id,name']), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PayrollSpreadsheet $payrollSpreadsheet)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['UPLOADED', 'PROCESSING', 'GENERATED', 'SENT', 'ERROR'])],
            'result_pdf_path' => 'nullable|string',
        ]);

        $payrollSpreadsheet->update($validated);

        return response()->json($payrollSpreadsheet);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PayrollSpreadsheet $payrollSpreadsheet)
    {
        // Optional: Delete file from storage
        if (Storage::exists($payrollSpreadsheet->storage_path)) {
            Storage::delete($payrollSpreadsheet->storage_path);
        }

        $payrollSpreadsheet->delete();

        return response()->noContent();
    }
}
