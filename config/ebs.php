<?php

/**
 * Konfiguracja integracji z systemem EBS (drugi system Stratton — generuje
 * faktury za obsługę i wysyła webhook po zaksięgowaniu zapłaty).
 *
 * Webhook URL (po stronie CRM):
 *   POST /api/ebs/webhook
 *   Header: X-EBS-Secret: <ten sam secret co po stronie EBS>
 *
 * Notatka projektowa (gdzie w EBS są faktury z prowizją Stratton):
 *   patrz EBS_PROJECT_NOTES_URL i ścieżka projektu poniżej.
 */

return [
    'webhook_secret' => env('EBS_WEBHOOK_SECRET', ''),

    // Outbound CRM → EBS (kierunek A: SIGNED → utwórz klienta w EBS).
    // Integracja jest AKTYWNA tylko gdy base_url ORAZ api_key są ustawione
    // (patrz App\Services\Ebs\EbsClient::enabled()). Puste = no-op (bezpieczne).
    'base_url' => env('EBS_BASE_URL', ''),           // np. https://ebs-wersja-natywna.vercel.app
    'api_key'  => env('EBS_API_KEY', ''),            // = INTERNAL_API_KEY po stronie EBS
    // Ścieżka endpointu EBS przyjmującego push klienta z CRM (Bearer api_key).
    'companies_sync_path' => env('EBS_COMPANIES_SYNC_PATH', '/api/integrations/crm/companies'),
    'timeout' => (int) env('EBS_HTTP_TIMEOUT', 10),

    // Lokalizacja kodu EBS (uzupełnij gdy będziesz robił integrację).
    'project_path' => env('EBS_PROJECT_PATH', ''),
    'project_notes_url' => env('EBS_PROJECT_NOTES_URL', ''),
];
