<?php

use App\Http\Controllers\Api\ClientNotesController;
use App\Http\Controllers\Api\LeadowiecController;
use Illuminate\Support\Facades\Route;

// Endpointy specyficzne dla leadowca
Route::middleware('can:leadowiec.opiekun.view')
    ->get('leadowiec/opiekun', [LeadowiecController::class, 'opiekun']);

Route::middleware('can:leadowiec.settlements.view')
    ->get('leadowiec/settlements', [LeadowiecController::class, 'settlements']);

// Notatki do klientów
Route::middleware('can:client-notes.view')
    ->get('clients/{client}/notes', [ClientNotesController::class, 'index']);

Route::middleware('can:client-notes.create')
    ->post('clients/{client}/notes', [ClientNotesController::class, 'store']);

Route::middleware('can:client-notes.update')
    ->put('clients/{client}/notes/{note}', [ClientNotesController::class, 'update']);

Route::middleware('can:client-notes.delete')
    ->delete('clients/{client}/notes/{note}', [ClientNotesController::class, 'destroy']);
