<?php

use App\Http\Controllers\ContextController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/context', ContextController::class)->middleware(['auth:sanctum', 'throttle:mcp']);
