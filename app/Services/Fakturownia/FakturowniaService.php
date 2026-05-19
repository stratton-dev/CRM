<?php

namespace App\Services\Fakturownia;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FakturowniaService
{
    private string $apiToken;
    private string $domain;

    public function __construct()
    {
        $this->apiToken = config('services.fakturownia.api_token', '');
        $this->domain   = config('services.fakturownia.domain', '');
    }

    /**
     * Zwraca faktury wystawione klientom z podanymi NIP-ami w danym miesiącu.
     * Fakturownia nie ma filtra po buyer_tax_no — pobieramy wszystkie za okres
     * i filtrujemy lokalnie.
     *
     * TODO: dla kont z >1000 faktur/miesiąc zaimplementować paginację po stronach.
     */
    public function getInvoicesByNips(array $nips, int $year, int $month): Collection
    {
        if (empty($nips) || !$this->apiToken || !$this->domain) {
            return collect();
        }

        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo   = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

        try {
            $response = Http::timeout(30)->get(
                "https://{$this->domain}.fakturownia.pl/invoices.json",
                [
                    'api_token' => $this->apiToken,
                    'period'    => 'own',
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                    'kind'      => 'vat',
                    'per_page'  => 1000,
                ]
            );

            if (!$response->successful()) {
                Log::warning('FakturowniaService: API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return collect();
            }

            $data = $response->json();
            if (!is_array($data)) {
                return collect();
            }

            return collect($data)
                ->filter(fn($inv) => in_array($inv['buyer_tax_no'] ?? '', $nips, true));

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('FakturowniaService: request exception', ['message' => $e->getMessage()]);
            return collect();
        } catch (\Exception $e) {
            Log::error('FakturowniaService: unexpected error', ['message' => $e->getMessage()]);
            return collect();
        }
    }
}
