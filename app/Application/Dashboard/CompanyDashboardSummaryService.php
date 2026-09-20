<?php

namespace App\Application\Dashboard;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\QueueEntryStatus;
use App\Domain\Booking\QueueStatus;
use App\Domain\Payments\TenantPaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Carbon\CarbonImmutable;

final class CompanyDashboardSummaryService
{
    /** @return array<string, mixed> */
    public function summarize(Tenant $tenant): array
    {
        $timezone = (string) ($tenant->timezone ?: config('app.timezone', 'UTC'));
        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay()->utc();
        $todayEnd = $now->endOfDay()->utc();

        $statusCounts = Appointment::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $todayQueues = Queue::query()
            ->with(['location', 'service'])
            ->withCount([
                'entries as waiting_count' => fn ($query) => $query->where('status', QueueEntryStatus::Waiting->value),
                'entries as serving_count' => fn ($query) => $query->where('status', QueueEntryStatus::Serving->value),
            ])
            ->where('business_date', $now->toDateString())
            ->orderByDesc('status')
            ->orderBy('next_position')
            ->limit(6)
            ->get();

        $collectedTodayMinor = (int) TenantPayment::query()
            ->where('status', TenantPaymentStatus::Succeeded->value)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('amount_minor');

        $upcomingAppointments = Appointment::query()
            ->with(['customer', 'staff', 'location', 'items'])
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
            ])
            ->where('starts_at', '>=', CarbonImmutable::now('UTC'))
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        $recentActivity = AppointmentStatusHistory::query()
            ->with(['appointment.customer', 'appointment.staff'])
            ->orderByDesc('changed_at')
            ->limit(6)
            ->get();

        return [
            'timezone' => $timezone,
            'currency' => strtoupper((string) ($tenant->default_currency ?: 'EGP')),
            'today' => $now->format('l, F j'),
            'metrics' => [
                'appointments_today' => (int) $statusCounts->sum(),
                'confirmed_today' => (int) ($statusCounts[AppointmentStatus::Confirmed->value] ?? 0),
                'pending_today' => (int) ($statusCounts[AppointmentStatus::Pending->value] ?? 0),
                'completed_today' => (int) ($statusCounts[AppointmentStatus::Completed->value] ?? 0),
                'active_customers' => Customer::query()->where('status', 'active')->count(),
                'active_staff' => Staff::query()->where('status', 'active')->count(),
                'active_services' => Service::query()->where('status', 'active')->count(),
                'active_locations' => Location::query()->where('status', 'active')->count(),
                'collected_today_minor' => $collectedTodayMinor,
                'open_queues' => (int) Queue::query()
                    ->where('business_date', $now->toDateString())
                    ->where('status', QueueStatus::Open->value)
                    ->count(),
                'waiting_queue_entries' => (int) $todayQueues->sum('waiting_count'),
                'serving_queue_entries' => (int) $todayQueues->sum('serving_count'),
            ],
            'upcoming_appointments' => $upcomingAppointments,
            'queues' => $todayQueues,
            'recent_activity' => $recentActivity,
        ];
    }
}
