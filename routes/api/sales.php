<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OffersController;
use App\Http\Controllers\Api\OfferItemsController;
use App\Http\Controllers\Api\OfferVerificationsController;
use App\Http\Controllers\Api\PayrollsController;
use App\Http\Controllers\Api\PayrollSpreadsheetController;
use App\Http\Controllers\Api\PayrollItemsController;
use App\Http\Controllers\Api\CalculationsController;
use App\Http\Controllers\Api\PayrollCalculationsController;
use App\Http\Controllers\Api\CalculatorConfigsController;

Route::apiResource('offers', OffersController::class)->only(['index', 'show'])->middleware('can:offers.view');
Route::apiResource('offers', OffersController::class)->only(['store'])->middleware('can:offers.create');
Route::apiResource('offers', OffersController::class)->only(['update'])->middleware('can:offers.update');
Route::apiResource('offers', OffersController::class)->only(['destroy'])->middleware('can:offers.delete');

Route::apiResource('offers.items', OfferItemsController::class)
    ->only(['index', 'show'])->middleware('can:offer-items.view')
    ->shallow()->parameters(['items' => 'item']);
Route::apiResource('offers.items', OfferItemsController::class)
    ->only(['store'])->middleware('can:offer-items.create')
    ->shallow()->parameters(['items' => 'item']);
Route::apiResource('offers.items', OfferItemsController::class)
    ->only(['update'])->middleware('can:offer-items.update')
    ->shallow()->parameters(['items' => 'item']);
Route::apiResource('offers.items', OfferItemsController::class)
    ->only(['destroy'])->middleware('can:offer-items.delete')
    ->shallow()->parameters(['items' => 'item']);

Route::apiResource('offers.verifications', OfferVerificationsController::class)
    ->only(['index', 'show'])->middleware('can:offer-verifications.view')
    ->shallow()->parameters(['verifications' => 'verification']);
Route::apiResource('offers.verifications', OfferVerificationsController::class)
    ->only(['store'])->middleware('can:offer-verifications.create')
    ->shallow()->parameters(['verifications' => 'verification']);
Route::apiResource('offers.verifications', OfferVerificationsController::class)
    ->only(['update'])->middleware('can:offer-verifications.update')
    ->shallow()->parameters(['verifications' => 'verification']);
Route::apiResource('offers.verifications', OfferVerificationsController::class)
    ->only(['destroy'])->middleware('can:offer-verifications.delete')
    ->shallow()->parameters(['verifications' => 'verification']);

Route::post('payroll-spreadsheets', [PayrollSpreadsheetController::class, 'store'])->middleware('can:payrolls.create');
Route::apiResource('payroll-spreadsheets', PayrollSpreadsheetController::class)->only(['index', 'show'])->middleware('can:payrolls.view');
Route::apiResource('payroll-spreadsheets', PayrollSpreadsheetController::class)->only(['update'])->middleware('can:payrolls.update');
Route::apiResource('payroll-spreadsheets', PayrollSpreadsheetController::class)->only(['destroy'])->middleware('can:payrolls.delete');

Route::apiResource('payrolls', PayrollsController::class)->only(['index', 'show'])->middleware('can:payrolls.view');
Route::apiResource('payrolls', PayrollsController::class)->only(['store'])->middleware('can:payrolls.create');
Route::apiResource('payrolls', PayrollsController::class)->only(['update'])->middleware('can:payrolls.update');
Route::apiResource('payrolls', PayrollsController::class)->only(['destroy'])->middleware('can:payrolls.delete');

Route::apiResource('payrolls.items', PayrollItemsController::class)
    ->only(['index', 'show'])->middleware('can:payroll-items.view')
    ->shallow()->parameters(['items' => 'item']);
Route::apiResource('payrolls.items', PayrollItemsController::class)
    ->only(['store'])->middleware('can:payroll-items.create')
    ->shallow()->parameters(['items' => 'item']);
Route::apiResource('payrolls.items', PayrollItemsController::class)
    ->only(['update'])->middleware('can:payroll-items.update')
    ->shallow()->parameters(['items' => 'item']);
Route::apiResource('payrolls.items', PayrollItemsController::class)
    ->only(['destroy'])->middleware('can:payroll-items.delete')
    ->shallow()->parameters(['items' => 'item']);

Route::apiResource('calculations', CalculationsController::class)->only(['index', 'show'])->middleware('can:calculations.view');
Route::apiResource('calculations', CalculationsController::class)->only(['store'])->middleware('can:calculations.create');
Route::apiResource('calculations', CalculationsController::class)->only(['update'])->middleware('can:calculations.update');
Route::apiResource('calculations', CalculationsController::class)->only(['destroy'])->middleware('can:calculations.delete');

Route::apiResource('payroll-calculations', PayrollCalculationsController::class)->only(['index', 'show'])->middleware('can:payroll-calculations.view');
Route::apiResource('payroll-calculations', PayrollCalculationsController::class)->only(['store'])->middleware('can:payroll-calculations.create');
Route::apiResource('payroll-calculations', PayrollCalculationsController::class)->only(['update'])->middleware('can:payroll-calculations.update');
Route::apiResource('payroll-calculations', PayrollCalculationsController::class)->only(['destroy'])->middleware('can:payroll-calculations.delete');

Route::apiResource('calculator-configs', CalculatorConfigsController::class)->only(['index', 'show'])->middleware('can:calculator-configs.view');
Route::apiResource('calculator-configs', CalculatorConfigsController::class)->only(['store'])->middleware('can:calculator-configs.create');
Route::apiResource('calculator-configs', CalculatorConfigsController::class)->only(['update'])->middleware('can:calculator-configs.update');
Route::apiResource('calculator-configs', CalculatorConfigsController::class)->only(['destroy'])->middleware('can:calculator-configs.delete');
