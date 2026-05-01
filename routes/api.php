<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\PublicOffersController;
use App\Http\Controllers\Api\AutentiWebhookController;
use App\Http\Controllers\Api\ImapServiceController;
use App\Http\Controllers\Api\GusController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MeetingsController;

Route::get('offers/{token}', PublicOffersController::class);
Route::post('autenti/webhook', AutentiWebhookController::class);

Broadcast::routes(['middleware' => ['supabase']]);

Route::prefix('v1')->group(function () {
    Route::get('imap-service/configs', [ImapServiceController::class, 'configs']);
    Route::get('gus', [GusController::class, 'byNip']);
});

Route::prefix('v1')->middleware('supabase')->group(function () {
    Route::get('me', MeController::class);
    Route::post('meetings/prospect', [MeetingsController::class, 'storeProspect']);

    require __DIR__ . '/api/structure.php';
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/users.php';
    require __DIR__ . '/api/clients.php';
    require __DIR__ . '/api/meetings.php';
    require __DIR__ . '/api/sales.php';
    require __DIR__ . '/api/notifications.php';
    require __DIR__ . '/api/documents.php';
    require __DIR__ . '/api/crm.php';
    require __DIR__ . '/api/chat.php';
    require __DIR__ . '/api/push.php';
    require __DIR__ . '/api/ai.php';
});

Route::options('/{any}', function () {
    return response('OK', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Auth-Token, Origin, Authorization');
})->where('any', '.*');
