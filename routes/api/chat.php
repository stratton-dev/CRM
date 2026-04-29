<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('chat')->controller(ChatController::class)->group(function () {
    Route::get('users', 'users');
    Route::post('groups', 'ensureGroup');
    Route::post('groups/create', 'createGroupWithMembers');
    Route::get('conversations', 'conversations');
    Route::post('conversations', 'findOrCreate');
    Route::delete('conversations/{id}', 'leave');
    Route::get('conversations/{id}/messages', 'messages');
    Route::post('conversations/{id}/messages', 'sendMessage');
    Route::put('conversations/{id}/read', 'markRead');
    Route::get('conversations/{id}/members', 'members');
    Route::post('conversations/{id}/members', 'addMember');
    Route::delete('conversations/{id}/members/{supabaseId}', 'removeMember');
});
