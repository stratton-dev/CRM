<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        "service name" => "Stratton API",
        "status" => "OK",
        "version" => "1.0.2",
    ]);
});
