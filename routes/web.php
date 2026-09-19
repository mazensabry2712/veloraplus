<?php

use App\Http\Controllers\Public\PublicHomeController;
use App\Http\Controllers\Public\PublicServiceController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Webhooks\KashierWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)
    ->middleware('public.tenant')
    ->name('public.home');

Route::get('/robots.txt', RobotsController::class)->name('seo.robots');

Route::middleware('public.tenant')->prefix('services')->name('public.services.')->group(function (): void {
    Route::get('/', [PublicServiceController::class, 'index'])->name('index');
    Route::get('/{slug}', [PublicServiceController::class, 'show'])
        ->where('slug', '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*')
        ->name('show');
});

Route::middleware(['public.tenant', 'noindex', 'throttle:30,1'])->group(function (): void {
    Route::get('/book/{slug}', [PublicBookingController::class, '__invoke'])
        ->where('slug', '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*')
        ->name('public.booking');
});

Route::get('/sitemap.xml', SitemapController::class)
    ->middleware('public.tenant')
    ->name('seo.sitemap');

Route::post('/webhooks/kashier/platform', KashierWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class);
