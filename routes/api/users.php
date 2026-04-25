<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\OrganizationsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\PermissionsController;
use App\Http\Controllers\Api\CrmViewPermissionsController;

Route::apiResource('users', UsersController::class)
    ->only(['index', 'show', 'update']);

Route::apiResource('organizations', OrganizationsController::class)
    ->only(['index', 'show'])
    ->middleware('can:organizations.view');
Route::apiResource('organizations', OrganizationsController::class)
    ->only(['store'])
    ->middleware('can:organizations.create');
Route::apiResource('organizations', OrganizationsController::class)
    ->only(['update'])
    ->middleware('can:organizations.update');
Route::apiResource('organizations', OrganizationsController::class)
    ->only(['destroy'])
    ->middleware('can:organizations.delete');

Route::apiResource('roles', RolesController::class)
    ->only(['index', 'show'])
    ->middleware('can:roles.view');
Route::apiResource('roles', RolesController::class)
    ->only(['store'])
    ->middleware('can:roles.create');
Route::apiResource('roles', RolesController::class)
    ->only(['update'])
    ->middleware('can:roles.update');
Route::apiResource('roles', RolesController::class)
    ->only(['destroy'])
    ->middleware('can:roles.delete');

Route::apiResource('permissions', PermissionsController::class)
    ->only(['index', 'show'])
    ->middleware('can:permissions.view');
Route::apiResource('permissions', PermissionsController::class)
    ->only(['store'])
    ->middleware('can:permissions.create');
Route::apiResource('permissions', PermissionsController::class)
    ->only(['update'])
    ->middleware('can:permissions.update');
Route::apiResource('permissions', PermissionsController::class)
    ->only(['destroy'])
    ->middleware('can:permissions.delete');

Route::get('crm-view-permissions', [CrmViewPermissionsController::class, 'index']);
Route::post('crm-view-permissions', [CrmViewPermissionsController::class, 'store']);
