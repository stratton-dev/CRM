<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Ebs\EbsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Kierunek A: wypycha podpisanego klienta (SIGNED) do EBS, który tworzy firmę
 * origin=CRM_SYNC. Idempotentny (pomija, gdy `ebs_company_id` już ustawione).
 * No-op, gdy integracja nieskonfigurowana (EbsClient::enabled() === false).
 */
class PushClientToEbsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $companyId)
    {
    }

    public function handle(EbsClient $ebs): void
    {
        if (!$ebs->enabled()) {
            return; // integracja wyłączona — nic nie robimy
        }

        /** @var Company|null $company */
        $company = Company::query()->with(['crmProfile.owner:id,email'])->find($this->companyId);
        if (!$company) {
            return;
        }
        if (!empty($company->ebs_company_id)) {
            return; // już zsynchronizowane — idempotencja
        }
        if (empty($company->nip)) {
            Log::warning('PushClientToEbsJob: brak NIP, pomijam', ['company_id' => $this->companyId]);
            return;
        }

        $profile = $company->crmProfile;
        $ownerEmail = $profile?->owner?->email;

        $payload = [
            'nip' => (string) $company->nip,
            'name' => (string) $company->name,
            'regon' => $company->regon,
            'krs' => $company->krs,
            'email' => $company->email,
            'phone' => $company->phone,
            'address_street' => $company->address_line1,
            'address_city' => $company->city,
            'address_zip' => $company->postal_code,
            // Przedstawiciel matchowany po EMAILU (EBS to inny projekt Supabase).
            'manager_email' => $ownerEmail,
            'fee_percent' => $profile?->service_fee_percent,
            // Korelacja zwrotna: EBS zapisze to jako external_crm_id.
            'external_crm_id' => (string) $company->id,
            'status' => 'SIGNED',
        ];

        $result = $ebs->createCompany($payload);
        if (!$result) {
            // EbsClient już zalogował; rzucamy, żeby zadziałał retry/backoff.
            throw new \RuntimeException('EBS createCompany failed for company ' . $this->companyId);
        }

        $ebsId = $result['id'] ?? $result['company']['id'] ?? null;
        $company->forceFill([
            'ebs_company_id' => $ebsId ? (string) $ebsId : 'SYNCED',
            'ebs_synced_at' => now(),
        ])->save();

        Log::info('PushClientToEbsJob: zsynchronizowano do EBS', [
            'company_id' => $this->companyId,
            'ebs_company_id' => $ebsId,
        ]);
    }
}
