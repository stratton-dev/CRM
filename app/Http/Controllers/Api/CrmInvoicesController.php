<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmInvoice;
use Illuminate\Http\Request;

class CrmInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmInvoice::query()->with('client:id,name');
        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        return $q->latest('issue_date')->paginate($request->integer('per_page', 50));
    }

    public function show(CrmInvoice $crmInvoice)
    {
        return $crmInvoice->load('client:id,name');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'number' => 'required|string|max:255|unique:crm_invoices,number',
            'client_id' => 'required|exists:companies,id',
            'issue_date' => 'required|date',
            'amount_net' => 'required|numeric',
            'amount_gross' => 'required|numeric',
            'service_fee_net' => 'required|numeric',
            'status' => 'nullable|in:PAID,UNPAID',
            'pdf_url' => 'nullable|string|max:255',
        ]);
        $invoice = CrmInvoice::create($data);
        return response()->json($invoice->load('client:id,name'), 201);
    }

    public function update(Request $request, CrmInvoice $crmInvoice)
    {
        $data = $request->validate([
            'number' => 'sometimes|required|string|max:255|unique:crm_invoices,number,'.$crmInvoice->id,
            'client_id' => 'sometimes|required|exists:companies,id',
            'issue_date' => 'sometimes|required|date',
            'amount_net' => 'sometimes|required|numeric',
            'amount_gross' => 'sometimes|required|numeric',
            'service_fee_net' => 'sometimes|required|numeric',
            'status' => 'nullable|in:PAID,UNPAID',
            'pdf_url' => 'nullable|string|max:255',
        ]);
        $crmInvoice->update($data);
        return $crmInvoice->load('client:id,name');
    }

    public function destroy(CrmInvoice $crmInvoice)
    {
        $crmInvoice->delete();
        return response()->noContent();
    }
}
