<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\DocumentTemplatesController;
use App\Http\Controllers\Api\AutentiStatusController;
use App\Http\Controllers\Api\AutentiDocumentsController;

Route::apiResource('documents', DocumentsController::class)->only(['index', 'show'])->middleware('can:documents.view');
Route::apiResource('documents', DocumentsController::class)->only(['store'])->middleware('can:documents.create');
Route::apiResource('documents', DocumentsController::class)->only(['update'])->middleware('can:documents.update');
Route::post('documents/{document}/autenti/sync', [DocumentsController::class, 'syncAutenti'])->middleware('can:documents.update');
Route::apiResource('documents', DocumentsController::class)->only(['destroy'])->middleware('can:documents.delete');

Route::get('document-templates/suggestions', [DocumentTemplatesController::class, 'suggestions'])->middleware('can:documents.view');
Route::get('document-templates/{documentTemplate}/download', [DocumentTemplatesController::class, 'download'])->middleware('can:documents.view');
Route::get('document-templates/{documentTemplate}/preview', [DocumentTemplatesController::class, 'preview'])->middleware('can:documents.view');
Route::apiResource('document-templates', DocumentTemplatesController::class)->only(['index'])->middleware('can:documents.view');
Route::apiResource('document-templates', DocumentTemplatesController::class)->only(['store'])->middleware('can:documents.create');
Route::apiResource('document-templates', DocumentTemplatesController::class)->only(['update'])->middleware('can:documents.update');
Route::apiResource('document-templates', DocumentTemplatesController::class)->only(['destroy'])->middleware('can:documents.delete');

Route::get('autenti/status', AutentiStatusController::class)->middleware('can:documents.view');

Route::apiResource('autenti-documents', AutentiDocumentsController::class)->only(['index', 'show'])->middleware('can:documents.view');
Route::post('autenti-documents/{autentiDocument}/sync', [AutentiDocumentsController::class, 'sync'])->middleware('can:documents.update');

Route::get('announcements', [\App\Http\Controllers\AnnouncementController::class, 'index']);
Route::get('announcements/manage', [\App\Http\Controllers\AnnouncementController::class, 'manage']);
Route::post('announcements', [\App\Http\Controllers\AnnouncementController::class, 'store']);
Route::match(['put', 'patch'], 'announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'update']);
Route::delete('announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'destroy']);
