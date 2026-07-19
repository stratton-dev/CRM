<?php

namespace App\Services\Ebs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klient HTTP do systemu EBS (kierunek A: CRM wypycha podpisanego klienta,
 * EBS tworzy firmę origin=CRM_SYNC). Integracja jest AKTYWNA wyłącznie gdy
 * skonfigurowano `ebs.base_url` i `ebs.api_key` — inaczej wszystko jest no-op
 * (zero ruchu na produkcji EBS do czasu ustawienia sekretów).
 *
 * Uwaga: EBS to ODRĘBNY projekt Supabase — przedstawiciel jest matchowany po
 * EMAILU (nie po supabase_id; UUID-y między projektami się nie zgadzają).
 */
class EbsClient
{
    public function enabled(): bool
    {
        return trim((string) config('ebs.base_url')) !== ''
            && trim((string) config('ebs.api_key')) !== '';
    }

    /**
     * Tworzy/synchronizuje firmę w EBS. Zwraca zdekodowane body EBS
     * (oczekiwane m.in. `id`, `external_crm_id`) albo null przy błędzie/wyłączeniu.
     *
     * @param array $payload {nip, name, regon?, krs?, email?, phone?, address_*?,
     *                        fee_percent?, manager_email?, external_crm_id, status}
     */
    public function createCompany(array $payload): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        $url = rtrim((string) config('ebs.base_url'), '/')
            . '/' . ltrim((string) config('ebs.companies_sync_path'), '/');

        try {
            $response = Http::timeout((int) config('ebs.timeout', 10))
                ->withToken((string) config('ebs.api_key'))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::warning('EbsClient: createCompany non-2xx', [
                    'status' => $response->status(),
                    'nip' => $payload['nip'] ?? null,
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                return null;
            }

            return is_array($response->json()) ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning('EbsClient: createCompany exception', [
                'nip' => $payload['nip'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
