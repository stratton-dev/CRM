<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Fakturownia\FakturowniaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadowiecController extends Controller
{
    public function opiekun(Request $request): JsonResponse
    {
        $leadowiec = $request->user();
        $opiekun = $leadowiec->opiekun()->select('id', 'name', 'email', 'phone')->first();

        return response()->json(['opiekun' => $opiekun]);
    }

    public function settlements(Request $request, FakturowniaService $fakturownia): JsonResponse
    {
        $leadowiec = $request->user();
        $month = $request->validate(['month' => 'nullable|string|regex:/^\d{4}-\d{2}$/'])['month']
            ?? Carbon::now()->format('Y-m');

        [$year, $mon] = explode('-', $month);

        $clients = $leadowiec->addedCompanies()
            ->whereHas('crmProfile', function ($q) {
                $q->whereIn('status', ['SIGNED', 'IN_TALKS', 'OFFER_GENERATED']);
            })
            ->select('id', 'name', 'nip')
            ->get();

        $nips = $clients->pluck('nip')->filter()->values()->all();

        $invoices = $fakturownia->getInvoicesByNips($nips, (int) $year, (int) $mon);

        $result = $clients->map(function ($client) use ($invoices, $leadowiec) {
            $clientInvoices = $invoices->filter(fn($inv) => ($inv['buyer_tax_no'] ?? '') === $client->nip);
            $sumNetto = $clientInvoices->sum(fn($inv) => (float) ($inv['price_net'] ?? 0));
            $commission = round($sumNetto * (float) $leadowiec->leadowiec_commission_rate, 2);

            return [
                'client_id'       => $client->id,
                'client_name'     => $client->name,
                'nip'             => $client->nip,
                'has_nip'         => !empty($client->nip),
                'invoices_count'  => $clientInvoices->count(),
                'sum_netto'       => $sumNetto,
                'commission_rate' => (float) $leadowiec->leadowiec_commission_rate,
                'commission'      => $commission,
            ];
        });

        return response()->json([
            'month'            => $month,
            'commission_rate'  => (float) $leadowiec->leadowiec_commission_rate,
            'total_commission' => $result->sum('commission'),
            'clients'          => $result->values(),
        ]);
    }
}
