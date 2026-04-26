<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\ClientContactsController;
use App\Http\Controllers\Api\ConsentsController;
use App\Http\Controllers\Api\ClientConsentsController;
use App\Http\Controllers\Api\CompaniesController;
use App\Http\Controllers\Api\EmployeesController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\Api\CandidatesController;

Route::get('clients/check-nip', [ClientsController::class, 'checkNip'])
    ->middleware('can:clients.view');
Route::apiResource('clients', ClientsController::class)->only(['index', 'show'])->middleware('can:clients.view');
Route::apiResource('clients', ClientsController::class)->only(['store'])->middleware('can:clients.create');
Route::apiResource('clients', ClientsController::class)->only(['update'])->middleware('can:clients.update');
Route::apiResource('clients', ClientsController::class)->only(['destroy'])->middleware('can:clients.delete');

Route::apiResource('clients.contacts', ClientContactsController::class)
    ->only(['index', 'show'])->middleware('can:client-contacts.view')
    ->shallow()->parameters(['contacts' => 'contact']);
Route::apiResource('clients.contacts', ClientContactsController::class)
    ->only(['store'])->middleware('can:client-contacts.create')
    ->shallow()->parameters(['contacts' => 'contact']);
Route::apiResource('clients.contacts', ClientContactsController::class)
    ->only(['update'])->middleware('can:client-contacts.update')
    ->shallow()->parameters(['contacts' => 'contact']);
Route::apiResource('clients.contacts', ClientContactsController::class)
    ->only(['destroy'])->middleware('can:client-contacts.delete')
    ->shallow()->parameters(['contacts' => 'contact']);

Route::apiResource('consents', ConsentsController::class)->only(['index', 'show'])->middleware('can:consents.view');
Route::get('consents/{consent}/file', [ConsentsController::class, 'file'])->name('consents.file')->middleware('can:consents.view');
Route::apiResource('consents', ConsentsController::class)->only(['store'])->middleware('can:consents.create');
Route::apiResource('consents', ConsentsController::class)->only(['update'])->middleware('can:consents.update');
Route::apiResource('consents', ConsentsController::class)->only(['destroy'])->middleware('can:consents.delete');

Route::get('clients/{client}/consents', [ClientConsentsController::class, 'index'])->middleware('can:client-consents.view')->name('client-consents.index');
Route::get('client-consents/{clientConsent}', [ClientConsentsController::class, 'show'])->middleware('can:client-consents.view')->name('client-consents.show');
Route::post('clients/{client}/consents', [ClientConsentsController::class, 'store'])->middleware('can:client-consents.create')->name('client-consents.store');
Route::match(['put', 'patch'], 'client-consents/{clientConsent}', [ClientConsentsController::class, 'update'])->middleware('can:client-consents.update')->name('client-consents.update');
Route::delete('client-consents/{clientConsent}', [ClientConsentsController::class, 'destroy'])->middleware('can:client-consents.delete')->name('client-consents.destroy');

Route::apiResource('companies', CompaniesController::class)->only(['index', 'show'])->middleware('can:companies.view');
Route::apiResource('companies', CompaniesController::class)->only(['store'])->middleware('can:companies.create');
Route::apiResource('companies', CompaniesController::class)->only(['update'])->middleware('can:companies.update');
Route::apiResource('companies', CompaniesController::class)->only(['destroy'])->middleware('can:companies.delete');

Route::apiResource('employees', EmployeesController::class)->only(['index', 'show'])->middleware('can:employees.view');
Route::apiResource('employees', EmployeesController::class)->only(['store'])->middleware('can:employees.create');
Route::apiResource('employees', EmployeesController::class)->only(['update'])->middleware('can:employees.update');
Route::apiResource('employees', EmployeesController::class)->only(['destroy'])->middleware('can:employees.delete');

Route::apiResource('leads', LeadsController::class);
Route::post('leads/{lead}/convert', [LeadsController::class, 'convert']);

Route::apiResource('candidates', CandidatesController::class);
