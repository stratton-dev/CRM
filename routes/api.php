<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\PublicOffersController;
use App\Http\Controllers\Api\AutentiWebhookController;
use App\Http\Controllers\Api\EbsWebhookController;
use App\Http\Controllers\Api\ImapServiceController;
use App\Http\Controllers\Api\GusController;
use App\Http\Controllers\Api\MeController;

Route::get('offers/{token}', PublicOffersController::class);
Route::post('autenti/webhook', AutentiWebhookController::class);
Route::post('ebs/webhook', EbsWebhookController::class);

Broadcast::routes(['middleware' => ['supabase']]);

Route::prefix('v1')->group(function () {
    // imap-service/configs broni się własnym tokenem serwisowym (fail-closed),
    // więc może zostać poza `supabase`. GUS był tu OMYŁKOWO bez auth — dowolny
    // anonim mógł odpytywać rejestr GUS naszym kluczem `GUS_BIR` (nadużycie limitu,
    // masowy scraping NIP). Przeniesiony do grupy `supabase` niżej.
    Route::get('imap-service/configs', [ImapServiceController::class, 'configs']);
});

Route::prefix('v1')->middleware('supabase')->group(function () {
    Route::get('me', MeController::class);
    // GUS przeniesiony tu z grupy bez-auth — wywoływany wyłącznie przez
    // zalogowany formularz nowego klienta (front dokłada Bearer do /v1/*).
    Route::get('gus', [GusController::class, 'byNip']);

    // Konfiguracja modelu ARP (produkt/cennik/gwarancja/lejek/prowizje) — read-only.
    Route::get('arp-config', [\App\Http\Controllers\Api\ArpConfigController::class, 'show']);
    // Moduł meetings wycofany (kontrolery/trasy/serwis usunięte). Tabele
    // `meetings`/`meeting_analysis` + modele zostają jako archiwum do czasu
    // ewentualnej migracji DROP po stabilizacji.

    require __DIR__ . '/api/structure.php';
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/users.php';
    require __DIR__ . '/api/clients.php';
    require __DIR__ . '/api/sales.php';
    require __DIR__ . '/api/notifications.php';
    require __DIR__ . '/api/documents.php';
    require __DIR__ . '/api/crm.php';
    require __DIR__ . '/api/chat.php';
    require __DIR__ . '/api/push.php';
    require __DIR__ . '/api/ai.php';
    require __DIR__ . '/api/pdf.php';
    require __DIR__ . '/api/leadowiec.php';
    require __DIR__ . '/api/commission.php';
});

Route::options('/{any}', function () {
    return response('OK', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Auth-Token, Origin, Authorization');
})->where('any', '.*');
