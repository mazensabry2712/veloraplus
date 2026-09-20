<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\QueueManager;
use App\Domain\Booking\QueueEntryStatus;
use App\Domain\Booking\QueueStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\EnqueueQueueEntryRequest;
use App\Http\Requests\Dashboard\QueueEntryReasonRequest;
use App\Http\Requests\Dashboard\StoreQueueRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Service;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class QueueController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext,
    ): View {
        Gate::authorize('viewAny', Queue::class);

        $tenant = $tenantContext->current();
        $timezone = $tenant->timezone ?: config('app.timezone', 'UTC');
        $date = trim($request->string('date')->toString());

        try {
            $businessDate = $date !== ''
                ? CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone)->toDateString()
                : CarbonImmutable::now($timezone)->toDateString();
        } catch (\Throwable) {
            $businessDate = CarbonImmutable::now($timezone)->toDateString();
        }

        $status = QueueStatus::tryFrom($request->string('status')->toString());
        $locationId = trim($request->string('location_id')->toString());
        $serviceId = trim($request->string('service_id')->toString());

        $queues = Queue::query()
            ->select([
                'id',
                'location_id',
                'service_id',
                'business_date',
                'status',
                'next_position',
            ])
            ->with([
                'location:id,name,timezone',
                'service:id,name,duration_minutes,price_minor,currency',
                'entries' => fn ($query) => $query
                    ->select([
                        'id',
                        'queue_id',
                        'customer_id',
                        'appointment_id',
                        'position',
                        'status',
                        'joined_at',
                        'called_at',
                        'completed_at',
                        'skipped_at',
                        'no_show_at',
                        'notes',
                    ])
                    ->with([
                        'customer:id,name,phone',
                        'appointment:id,starts_at,status',
                    ])
                    ->orderBy('position')
                    ->limit(50),
            ])
            ->withCount([
                'entries',
                'entries as waiting_entries_count' => fn ($query) => $query->where('status', QueueEntryStatus::Waiting->value),
                'entries as serving_entries_count' => fn ($query) => $query->where('status', QueueEntryStatus::Serving->value),
            ])
            ->where('business_date', $businessDate)
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($locationId !== '', fn ($query) => $query->where('location_id', $locationId))
            ->when($serviceId !== '', fn ($query) => $query->where('service_id', $serviceId))
            ->orderBy('location_id')
            ->orderBy('service_id')
            ->paginate(15)
            ->withQueryString();

        $locations = Location::query()
            ->select(['id', 'name'])
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $services = Service::query()
            ->select(['id', 'name', 'duration_minutes', 'price_minor', 'currency'])
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $customers = Customer::query()
            ->select(['id', 'name', 'phone'])
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $appointments = Appointment::query()
            ->select(['id', 'customer_id', 'staff_id', 'location_id', 'starts_at', 'status'])
            ->with([
                'customer:id,name',
                'staff:id,name',
                'location:id,name',
                'items:id,appointment_id,service_name',
            ])
            ->whereIn('status', [
                'pending',
                'confirmed',
            ])
            ->whereBetween(
                'starts_at',
                [
                    CarbonImmutable::parse($businessDate, $timezone)->startOfDay()->utc(),
                    CarbonImmutable::parse($businessDate, $timezone)->endOfDay()->utc(),
                ],
            )
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        return view('dashboard.booking.queues', [
            'tenant' => $tenant,
            'timezone' => $timezone,
            'businessDate' => $businessDate,
            'queues' => $queues,
            'statusOptions' => [
                'open' => 'Open',
                'closed' => 'Closed',
            ],
            'locations' => $locations,
            'services' => $services,
            'customers' => $customers,
            'appointments' => $appointments,
            'canManage' => auth()->user()->can('booking.queues.manage'),
        ]);
    }

    public function store(
        StoreQueueRequest $request,
        QueueManager $manager,
    ): RedirectResponse {
        Gate::authorize('create', Queue::class);

        try {
            $data = $request->validated();

            $manager->createQueue(
                location: Location::query()->findOrFail($data['location_id']),
                service: Service::query()->findOrFail($data['service_id']),
                businessDate: $data['business_date'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Queue created successfully.');
    }

    public function open(Queue $queue, QueueManager $manager): RedirectResponse
    {
        Gate::authorize('manage', $queue);

        try {
            $manager->open($queue);
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Queue opened successfully.');
    }

    public function close(Queue $queue, QueueManager $manager): RedirectResponse
    {
        Gate::authorize('manage', $queue);

        try {
            $manager->close($queue);
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Queue closed successfully.');
    }

    public function enqueue(
        EnqueueQueueEntryRequest $request,
        Queue $queue,
        QueueManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', $queue);

        try {
            $data = $request->validated();

            $manager->enqueue(
                queue: $queue,
                customer: Customer::query()->findOrFail($data['customer_id']),
                appointment: isset($data['appointment_id'])
                    ? Appointment::query()->findOrFail($data['appointment_id'])
                    : null,
                idempotencyKey: $data['idempotency_key'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Customer added to queue successfully.');
    }

    public function callNext(Queue $queue, QueueManager $manager): RedirectResponse
    {
        Gate::authorize('manage', $queue);

        try {
            $manager->callNext($queue);
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Next queue entry called successfully.');
    }

    public function complete(Queue $queue, QueueEntry $entry, QueueManager $manager): RedirectResponse
    {
        Gate::authorize('manageEntry', $entry);
        $this->assertEntryBelongsToQueue($entry, $queue);

        try {
            $manager->complete($entry);
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Queue entry completed successfully.');
    }

    public function skip(
        QueueEntryReasonRequest $request,
        Queue $queue,
        QueueEntry $entry,
        QueueManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageEntry', $entry);
        $this->assertEntryBelongsToQueue($entry, $queue);

        try {
            $manager->skip($entry, $request->validated('reason'));
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Queue entry skipped successfully.');
    }

    public function noShow(
        QueueEntryReasonRequest $request,
        Queue $queue,
        QueueEntry $entry,
        QueueManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageEntry', $entry);
        $this->assertEntryBelongsToQueue($entry, $queue);

        try {
            $manager->markNoShow($entry, $request->validated('reason'));
        } catch (DomainException $exception) {
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Queue entry marked as no-show.');
    }

    private function assertEntryBelongsToQueue(QueueEntry $entry, Queue $queue): void
    {
        if ((string) $entry->queue_id !== (string) $queue->getKey()) {
            abort(404);
        }
    }
}
