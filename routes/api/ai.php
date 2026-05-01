<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\Admin\KnowledgeBaseController;

// AI Chatbot
Route::prefix('ai-chat')->group(function () {
    Route::get('conversations', [AiChatController::class, 'indexConversations']);
    Route::post('conversations', [AiChatController::class, 'createConversation']);
    Route::get('conversations/{id}', [AiChatController::class, 'showConversation']);
    Route::delete('conversations/{id}', [AiChatController::class, 'destroyConversation']);
    Route::post('conversations/{id}/messages', [AiChatController::class, 'sendMessage']);
    Route::post('quick', [AiChatController::class, 'quickMessage']);
});

// Knowledge Base — tylko ADMIN
Route::prefix('admin/knowledge-base')->middleware('can:roles.delete')->group(function () {
    Route::get('/', [KnowledgeBaseController::class, 'index']);
    Route::post('/', [KnowledgeBaseController::class, 'store']);
    Route::delete('/{id}', [KnowledgeBaseController::class, 'destroy']);
    Route::get('/{id}/status', [KnowledgeBaseController::class, 'status']);
});
