<?php

use App\Http\Controllers\Api\PdfController;
use Illuminate\Support\Facades\Route;

Route::prefix('pdf')->group(function () {
    Route::post('generate-offer', [PdfController::class, 'generateOffer']);
});
