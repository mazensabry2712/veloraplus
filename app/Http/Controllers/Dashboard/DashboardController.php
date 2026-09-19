<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\TenantEntitlement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        if (! $context->check()) {
            abort(404);
        }

        $tenant = $context->current();
        $account = $request->user();

        $membership = $tenant->memberships()
            ->where('account_id', $account->getKey())
            ->where('status', 'active')
            ->firstOrFail();

        $timezone = $tenant->timezone ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::now($timezone);

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->latest('created_at')
            ->first();

        $activeEntitlements = TenantEntitlement::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->count();

        $usage = [
            'customers' => Customer::query()->count(),
            'active_staff' => Staff::query()->where('status', 'active')->count(),
            'active_services' => Service::query()->where('status', 'active')->count(),
            'appointments_today' => Appointment::query()
                ->whereBetween('starts_at', [
                    $today->startOfDay()->utc(),
                    $today->endOfDay()->utc(),
                ])
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->count(),
            'waiting_queue_entries' => QueueEntry::query()
                ->where('status', 'waiting')
                ->count(),
        ];

        return view('dashboard.index', [
            'tenant' => $tenant,
            'account' => $account,
            'membership' => $membership,
            'subscription' => $subscription,
            'activeEntitlements' => $activeEntitlements,
            'usage' => $usage,
            'timezone' => $timezone,
            'today' => $today,
        ]);
    }
}
