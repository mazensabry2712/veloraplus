<?php

use App\Http\Controllers\Public\PublicHomeController;
use App\Http\Controllers\Public\PublicServiceController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Dashboard\CompanyDashboardController;
use App\Http\Controllers\Dashboard\CompanyProfileController;
use App\Http\Controllers\Dashboard\LocationController;
use App\Http\Controllers\Dashboard\TenantSettingsController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\CustomerController;
use App\Http\Controllers\Dashboard\TenantMembershipController;
use App\Http\Controllers\Dashboard\TenantRoleController;
use App\Http\Controllers\Dashboard\BookingServiceController;
use App\Http\Controllers\Dashboard\BookingAvailabilityController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\QueueController;
use App\Http\Controllers\Dashboard\TenantPaymentController;
use App\Http\Controllers\Dashboard\PlatformBillingController;
use App\Http\Controllers\Dashboard\ModuleMarketplaceController;
use App\Http\Controllers\Webhooks\KashierWebhookController;
use App\Http\Controllers\Webhooks\KashierTenantWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)
    ->middleware('public.tenant')
    ->name('public.home');

Route::get('/robots.txt', RobotsController::class)->name('seo.robots');
Route::get('/dashboard', CompanyDashboardController::class)
    ->middleware(['auth', 'tenant', 'tenant.member', 'noindex'])
    ->name('dashboard');

Route::middleware(['auth', 'tenant', 'tenant.member', 'noindex'])->group(function (): void {
    Route::post('/dashboard/company/profile', [CompanyProfileController::class, 'update'])
        ->name('company.profile.update');

    Route::post('/dashboard/company/locations', [LocationController::class, 'store'])
        ->name('company.locations.store');

    Route::patch('/dashboard/company/locations/{location}', [LocationController::class, 'update'])
        ->name('company.locations.update');

    Route::delete('/dashboard/company/locations/{location}', [LocationController::class, 'destroy'])
        ->name('company.locations.destroy');

    Route::put('/dashboard/company/settings', [TenantSettingsController::class, 'update'])
        ->name('company.settings.update');

    Route::post('/dashboard/company/staff', [StaffController::class, 'store'])
        ->name('company.staff.store');

    Route::patch('/dashboard/company/staff/{staff}', [StaffController::class, 'update'])
        ->name('company.staff.update');

    Route::delete('/dashboard/company/staff/{staff}', [StaffController::class, 'destroy'])
        ->name('company.staff.destroy');

    Route::post('/dashboard/company/customers', [CustomerController::class, 'store'])
        ->name('company.customers.store');

    Route::patch('/dashboard/company/customers/{customer}', [CustomerController::class, 'update'])
        ->name('company.customers.update');

    Route::delete('/dashboard/company/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('company.customers.destroy');

    Route::post('/dashboard/company/users', [TenantMembershipController::class, 'store'])
        ->name('company.users.store');

    Route::patch('/dashboard/company/users/{membership}', [TenantMembershipController::class, 'update'])
        ->name('company.users.update');

    Route::delete('/dashboard/company/users/{membership}', [TenantMembershipController::class, 'destroy'])
        ->name('company.users.destroy');

    Route::post('/dashboard/company/roles', [TenantRoleController::class, 'store'])
        ->name('company.roles.store');

    Route::patch('/dashboard/company/roles/{role}', [TenantRoleController::class, 'update'])
        ->name('company.roles.update');

    Route::delete('/dashboard/company/roles/{role}', [TenantRoleController::class, 'destroy'])
        ->name('company.roles.destroy');
});

Route::middleware(['auth', 'tenant', 'tenant.member', 'entitled:booking.services', 'noindex'])
    ->prefix('dashboard/booking/services')
    ->name('dashboard.booking.services.')
    ->group(function (): void {
        Route::post('/', [BookingServiceController::class, 'store'])
            ->name('store');

        Route::patch('/{service}', [BookingServiceController::class, 'update'])
            ->name('update');

        Route::delete('/{service}', [BookingServiceController::class, 'destroy'])
            ->name('destroy');
});

Route::middleware(['auth', 'tenant', 'tenant.member', 'entitled:booking.availability', 'noindex'])
    ->prefix('dashboard/booking/availability')
    ->name('dashboard.booking.availability.')
    ->group(function (): void {
        Route::post('/staff/{staff}/services/{service}', [BookingAvailabilityController::class, 'assignService'])
            ->middleware('entitled:booking.services')
            ->name('assign-service');

        Route::delete('/staff/{staff}/services/{service}', [BookingAvailabilityController::class, 'unassignService'])
            ->middleware('entitled:booking.services')
            ->name('unassign-service');

        Route::post('/staff/{staff}/working-hours', [BookingAvailabilityController::class, 'storeWorkingHour'])
            ->name('working-hours.store');

        Route::patch('/staff/{staff}/working-hours/{workingHour}', [BookingAvailabilityController::class, 'updateWorkingHour'])
            ->name('working-hours.update');

        Route::delete('/staff/{staff}/working-hours/{workingHour}', [BookingAvailabilityController::class, 'destroyWorkingHour'])
            ->name('working-hours.destroy');

        Route::post('/staff/{staff}/working-hours/{workingHour}/breaks', [BookingAvailabilityController::class, 'storeBreak'])
            ->name('breaks.store');

        Route::patch('/staff/{staff}/working-hours/{workingHour}/breaks/{break}', [BookingAvailabilityController::class, 'updateBreak'])
            ->name('breaks.update');

        Route::delete('/staff/{staff}/working-hours/{workingHour}/breaks/{break}', [BookingAvailabilityController::class, 'destroyBreak'])
            ->name('breaks.destroy');

        Route::post('/staff/{staff}/time-off', [BookingAvailabilityController::class, 'storeTimeOff'])
            ->name('time-off.store');

        Route::patch('/staff/{staff}/time-off/{timeOff}', [BookingAvailabilityController::class, 'updateTimeOff'])
            ->name('time-off.update');

        Route::delete('/staff/{staff}/time-off/{timeOff}', [BookingAvailabilityController::class, 'destroyTimeOff'])
            ->name('time-off.destroy');
    });

Route::middleware(['auth', 'tenant', 'tenant.member', 'entitled:booking.appointments', 'noindex'])
    ->prefix('dashboard/booking/appointments')
    ->name('dashboard.booking.appointments.')
    ->group(function (): void {
        Route::post('/', [AppointmentController::class, 'store'])
            ->name('store');

        Route::patch('/{appointment}', [AppointmentController::class, 'reschedule'])
            ->name('reschedule');

        Route::post('/{appointment}/confirm', [AppointmentController::class, 'confirm'])
            ->name('confirm');

        Route::post('/{appointment}/complete', [AppointmentController::class, 'complete'])
            ->name('complete');

        Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->name('cancel');

        Route::post('/{appointment}/no-show', [AppointmentController::class, 'noShow'])
            ->name('no-show');
    });

Route::middleware(['auth', 'tenant', 'tenant.member', 'entitled:booking.queues', 'noindex'])
    ->prefix('dashboard/booking/queues')
    ->name('dashboard.booking.queues.')
    ->group(function (): void {
        Route::post('/', [QueueController::class, 'store'])
            ->name('store');

        Route::post('/{queue}/open', [QueueController::class, 'open'])
            ->name('open');

        Route::post('/{queue}/close', [QueueController::class, 'close'])
            ->name('close');

        Route::post('/{queue}/entries', [QueueController::class, 'enqueue'])
            ->name('entries.store');

        Route::post('/{queue}/call-next', [QueueController::class, 'callNext'])
            ->name('call-next');

        Route::post('/{queue}/entries/{entry}/complete', [QueueController::class, 'complete'])
            ->name('entries.complete');

        Route::post('/{queue}/entries/{entry}/skip', [QueueController::class, 'skip'])
            ->name('entries.skip');

        Route::post('/{queue}/entries/{entry}/no-show', [QueueController::class, 'noShow'])
            ->name('entries.no-show');
    });

Route::middleware(['auth', 'tenant', 'tenant.member', 'entitled:booking.payments', 'noindex'])
    ->prefix('dashboard/booking/payments')
    ->name('dashboard.booking.payments.')
    ->group(function (): void {
        Route::post('/appointments/{appointment}', [TenantPaymentController::class, 'createCheckout'])
            ->name('appointments.checkout');

        Route::post('/{payment}/reconcile', [TenantPaymentController::class, 'reconcile'])
            ->name('reconcile');

        Route::post('/{payment}/refund', [TenantPaymentController::class, 'refund'])
            ->name('refund');
    });

Route::middleware(['auth', 'tenant', 'tenant.member', 'noindex'])
    ->prefix('dashboard/billing')
    ->name('dashboard.billing.')
    ->group(function (): void {
        Route::post('/invoices/{invoice}/checkout', [PlatformBillingController::class, 'checkoutInvoice'])
            ->name('invoices.checkout');

        Route::post('/invoices/{invoice}/void', [PlatformBillingController::class, 'voidInvoice'])
            ->name('invoices.void');

        Route::post('/payments/{payment}/refund', [PlatformBillingController::class, 'refundPayment'])
            ->name('payments.refund');

        Route::post('/credits', [PlatformBillingController::class, 'issueCredit'])
            ->name('credits.store');

        Route::post('/subscription/{subscription}/upgrade', [PlatformBillingController::class, 'requestUpgrade'])
            ->name('subscription.upgrade');

        Route::post('/subscription/{subscription}/items/{item}/downgrade', [PlatformBillingController::class, 'scheduleDowngrade'])
            ->name('subscription.downgrade');

        Route::post('/subscription/{subscription}/cancel', [PlatformBillingController::class, 'cancelSubscription'])
            ->name('subscription.cancel');\n    });

Route::middleware(['auth', 'tenant', 'tenant.member', 'noindex'])
    ->prefix('dashboard/marketplace')
    ->name('dashboard.marketplace.')
    ->group(function (): void {
        Route::post('/purchase', [ModuleMarketplaceController::class, 'purchase'])
            ->name('purchase');
    });
    });

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

    Route::post('/book/{slug}', [PublicBookingController::class, 'store'])
        ->where('slug', '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*')
        ->name('public.booking.store');
});

Route::get('/sitemap.xml', SitemapController::class)
    ->middleware('public.tenant')
    ->name('seo.sitemap');

Route::post('/webhooks/kashier/platform', KashierWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class);

Route::post('/webhooks/kashier/tenant', KashierTenantWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class);
