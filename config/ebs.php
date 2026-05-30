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

    // URL do EBS (gdy CRM ma sam pull-ować dane, na razie nie używane).
    'base_url' => env('EBS_BASE_URL', ''),
    'api_key' => env('EBS_API_KEY', ''),

    // Lokalizacja kodu EBS (uzupełnij gdy będziesz robił integrację).
    'project_path' => env('EBS_PROJECT_PATH', ''),
    'project_notes_url' => env('EBS_PROJECT_NOTES_URL', ''),
];
