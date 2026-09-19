<?php

use App\Http\Controllers\Webhooks\KashierWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/kashier/platform', KashierWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class);
