<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CompaniesController;
use App\Http\Controllers\Api\EmployeesController;
use App\Http\Controllers\Api\OffersController;
use App\Http\Controllers\Api\OfferItemsController;
use App\Http\Controllers\Api\PayrollCalculationsController;
use App\Http\Controllers\Api\CalculatorConfigsController;

Route::prefix('v1')->group(function () {
    // Companies and Employees
    Route::apiResource('companies', CompaniesController::class);
    Route::apiResource('employees', EmployeesController::class);

    // Offers and nested offer items
    Route::apiResource('offers', OffersController::class);

    // Nested items under offers with shallow routes for item operations
    Route::apiResource('offers.items', OfferItemsController::class)
        ->shallow()
        ->parameters([
            'items' => 'item', // ensures {item} parameter name
        ]);

    // Payroll calculations
    Route::apiResource('payroll-calculations', PayrollCalculationsController::class);

    // Calculator configs
    Route::apiResource('calculator-configs', CalculatorConfigsController::class);
});
