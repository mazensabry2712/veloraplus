<?php

use App\Application\SEO\SeoManager;
use App\Http\Controllers\Public\PublicHomeController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Webhooks\KashierWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)
    ->middleware('public.tenant')
    ->name('public.home');

Route::get('/robots.txt', RobotsController::class)->name('seo.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');

Route::post('/webhooks/kashier/platform', KashierWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class);
