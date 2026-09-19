<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\QueueManager;
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
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class QueueController extends Controller
{
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
