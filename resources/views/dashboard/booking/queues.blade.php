@extends('layouts.dashboard')

@section('title', 'Queue')
@section('heading', 'Queue')

@section('content')
    @php
        use App\Domain\Booking\QueueEntryStatus;
        use App\Domain\Booking\QueueStatus;

        $entryStatusLabels = [
            'waiting' => 'Waiting',
            'serving' => 'Serving',
            'completed' => 'Completed',
            'skipped' => 'Skipped',
            'no_show' => 'No Show',
        ];
    @endphp

    <x-dashboard.page-header
        title="Queue"
        description="Operate daily queues by location and service without bypassing QueueManager rules."
    />

    <div class="mt-6 space-y-6">
        <x-dashboard.card>
            <form method="GET" action="{{ route('dashboard.booking.queues.index') }}" class="grid gap-4 lg:grid-cols-[10rem_1fr_1fr_auto] lg:items-end">
                <div>
                    <label for="queue-date" class="mb-1 block text-xs font-medium text-muted">Business date</label>
                    <input
                        id="queue-date"
                        name="date"
                        type="date"
                        value="{{ $businessDate }}"
                        class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                    >
                </div>

                <div>
                    <label for="queue-location" class="mb-1 block text-xs font-medium text-muted">Location</label>
                    <select id="queue-location" name="location_id" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="">All locations</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->getKey() }}" @selected(request('location_id') === (string) $location->getKey())>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="queue-service" class="mb-1 block text-xs font-medium text-muted">Service</label>
                    <select id="queue-service" name="service_id" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="">All services</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->getKey() }}" @selected(request('service_id') === (string) $service->getKey())>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <select name="status" class="min-h-10 rounded-lg border border-border bg-white px-3 text-sm" aria-label="Queue status">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-dashboard.button type="submit" size="sm">Filter</x-dashboard.button>
                    @if (request()->hasAny(['date', 'location_id', 'service_id', 'status']))
                        <a href="{{ route('dashboard.booking.queues.index') }}" class="inline-flex min-h-9 items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary hover:bg-surface">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </x-dashboard.card>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-5">
                @forelse ($queues as $queue)
                    @php
                        $queueStatus = $queue->status instanceof QueueStatus
                            ? $queue->status->value
                            : (string) $queue->status;
                        $queueTimezone = $queue->location?->timezone ?: $timezone;
                        $entryStatusVariant = [
                            'waiting' => 'warning',
                            'serving' => 'info',
                            'completed' => 'success',
                            'skipped' => 'neutral',
                            'no_show' => 'danger',
                        ];
                    @endphp

                    <x-dashboard.card>
                        <div class="flex flex-col gap-4 border-b border-border pb-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-secondary">{{ $queue->service?->name ?? 'Service' }}</h2>
                                    <x-dashboard.badge :variant="$queueStatus === 'open' ? 'success' : 'neutral'">
                                        {{ $queueStatus === 'open' ? 'Open' : 'Closed' }}
                                    </x-dashboard.badge>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                                    <span>{{ $queue->location?->name ?? 'Location' }}</span>
                                    <span>{{ $queue->business_date }}</span>
                                    <span>{{ $queueTimezone }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full border border-border bg-surface px-2.5 py-1 text-muted">{{ $queue->entries_count }} total</span>
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-800">{{ $queue->waiting_entries_count }} waiting</span>
                                    <span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-sky-700">{{ $queue->serving_entries_count }} serving</span>
                                </div>
                            </div>

                            @if ($canManage)
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($queueStatus === 'open')
                                        <form method="POST" action="{{ route('dashboard.booking.queues.call-next', $queue) }}">
                                            @csrf
                                            <x-dashboard.button type="submit" size="sm">Call Next</x-dashboard.button>
                                        </form>

                                        <form method="POST" action="{{ route('dashboard.booking.queues.close', $queue) }}">
                                            @csrf
                                            <x-dashboard.button variant="danger" type="submit" size="sm">Close Queue</x-dashboard.button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('dashboard.booking.queues.open', $queue) }}">
                                            @csrf
                                            <x-dashboard.button type="submit" size="sm">Open Queue</x-dashboard.button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($canManage && $queueStatus === 'open')
                            <details class="mt-5">
                                <summary class="cursor-pointer text-sm font-medium text-primary">Add Customer</summary>
                                <form method="POST" action="{{ route('dashboard.booking.queues.entries.store', $queue) }}" class="mt-3 rounded-xl border border-border bg-surface p-4">
                                    @csrf
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-muted">Customer</label>
                                            <select name="customer_id" required class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                <option value="">Select customer</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->getKey() }}">
                                                        {{ $customer->name }}@if($customer->phone) — {{ $customer->phone }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-muted">Appointment <span class="font-normal">(optional)</span></label>
                                            <select name="appointment_id" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                <option value="">Walk-in / no appointment</option>
                                                @foreach ($appointments as $appointment)
                                                    @php
                                                        $appointmentStart = $appointment->starts_at->setTimezone($queueTimezone);
                                                    @endphp
                                                    <option value="{{ $appointment->getKey() }}">
                                                        {{ $appointment->customer?->name ?? 'Customer' }} · {{ $appointment->items->first()?->service_name ?? 'Appointment' }} · {{ $appointmentStart->format('H:i') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-muted">Idempotency key <span class="font-normal">(optional)</span></label>
                                            <input name="idempotency_key" maxlength="190" placeholder="queue-2026-0001" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-muted">Notes <span class="font-normal">(optional)</span></label>
                                            <input name="notes" maxlength="1000" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                        </div>
                                    </div>

                                    <div class="mt-4 flex justify-end">
                                        <x-dashboard.button type="submit" size="sm">Add to Queue</x-dashboard.button>
                                    </div>
                                </form>
                            </details>
                        @endif

                        <div class="mt-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-medium text-secondary">Queue Entries</h3>
                                    <p class="mt-1 text-xs text-muted">Showing up to 50 entries ordered by queue position.</p>
                                </div>
                                <span class="text-xs text-muted">Next position {{ $queue->next_position }}</span>
                            </div>

                            <div class="space-y-3">
                                @forelse ($queue->entries as $entry)
                                    @php
                                        $entryStatus = $entry->status instanceof QueueEntryStatus
                                            ? $entry->status->value
                                            : (string) $entry->status;
                                        $entryLabel = $entryStatusLabels[$entryStatus] ?? str($entryStatus)->replace('_', ' ')->title();
                                    @endphp

                                    <div class="rounded-xl border border-border bg-surface p-4">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border bg-white text-sm font-semibold text-secondary">
                                                    {{ $entry->position }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-secondary">{{ $entry->customer?->name ?? 'Customer' }}</div>
                                                    <div class="mt-1 text-xs text-muted">
                                                        @if ($entry->customer?->phone){{ $entry->customer->phone }} · @endif
                                                        Joined {{ $entry->joined_at?->setTimezone($queueTimezone)?->format('H:i') }}
                                                        @if ($entry->appointment)
                                                            · Appointment {{ $entry->appointment->starts_at?->setTimezone($queueTimezone)?->format('H:i') }}
                                                        @endif
                                                    </div>
                                                    @if ($entry->notes)
                                                        <div class="mt-2 text-xs leading-5 text-muted">{{ $entry->notes }}</div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                <x-dashboard.badge :variant="$entryStatusVariant[$entryStatus] ?? 'neutral'">
                                                    {{ $entryLabel }}
                                                </x-dashboard.badge>

                                                @if ($canManage)
                                                    @if ($entryStatus === 'serving')
                                                        <form method="POST" action="{{ route('dashboard.booking.queues.entries.complete', [$queue, $entry]) }}">
                                                            @csrf
                                                            <x-dashboard.button type="submit" size="sm">Complete</x-dashboard.button>
                                                        </form>
                                                    @endif

                                                    @if (in_array($entryStatus, ['waiting', 'serving'], true))
                                                        <details>
                                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Skip</summary>
                                                            <form method="POST" action="{{ route('dashboard.booking.queues.entries.skip', [$queue, $entry]) }}" class="mt-3 w-[min(22rem,calc(100vw-3rem))] rounded-xl border border-border bg-white p-4 shadow-sm">
                                                                @csrf
                                                                <label class="mb-1 block text-xs font-medium text-muted">Reason <span class="font-normal">(optional)</span></label>
                                                                <input name="reason" maxlength="255" class="block w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                                                                <div class="mt-3 flex justify-end">
                                                                    <x-dashboard.button type="submit" size="sm">Skip Entry</x-dashboard.button>
                                                                </div>
                                                            </form>
                                                        </details>

                                                        <details>
                                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-red-200 px-3 text-xs font-medium text-red-700 [&::-webkit-details-marker]:hidden">No-show</summary>
                                                            <form method="POST" action="{{ route('dashboard.booking.queues.entries.no-show', [$queue, $entry]) }}" class="mt-3 w-[min(22rem,calc(100vw-3rem))] rounded-xl border border-border bg-white p-4 shadow-sm">
                                                                @csrf
                                                                <label class="mb-1 block text-xs font-medium text-muted">Reason <span class="font-normal">(optional)</span></label>
                                                                <input name="reason" maxlength="255" class="block w-full rounded-lg border border-border px-3 py-2.5 text-sm">
                                                                <div class="mt-3 flex justify-end">
                                                                    <x-dashboard.button variant="danger" type="submit" size="sm">Mark No-show</x-dashboard.button>
                                                                </div>
                                                            </form>
                                                        </details>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-dashed border-border p-6 text-sm text-muted">No customers are currently in this queue.</p>
                                @endforelse
                            </div>
                        </div>
                    </x-dashboard.card>
                @empty
                    <x-dashboard.card>
                        <div class="px-6 py-12 text-center">
                            <h2 class="font-medium text-secondary">No queues for {{ $businessDate }}</h2>
                            <p class="mt-2 text-sm text-muted">Create a queue for a location and service to start operating walk-ins.</p>
                        </div>
                    </x-dashboard.card>
                @endforelse

                <x-dashboard.pagination :paginator="$queues" />
            </div>

            @if ($canManage)
                <x-dashboard.card title="Create Queue" description="Queues are unique by location, service, and business date.">
                    @if ($locations->isEmpty() || $services->isEmpty())
                        <div class="rounded-xl border border-dashed border-border bg-surface p-5 text-sm leading-6 text-muted">
                            Active locations and services are required before creating a queue.
                        </div>
                    @else
                        <form method="POST" action="{{ route('dashboard.booking.queues.store') }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="create-queue-location" class="mb-1 block text-xs font-medium text-muted">Location</label>
                                <select id="create-queue-location" name="location_id" required class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                    <option value="">Select location</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->getKey() }}" @selected(old('location_id') === (string) $location->getKey())>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="create-queue-service" class="mb-1 block text-xs font-medium text-muted">Service</label>
                                <select id="create-queue-service" name="service_id" required class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                    <option value="">Select service</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->getKey() }}" @selected(old('service_id') === (string) $service->getKey())>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="create-queue-date" class="mb-1 block text-xs font-medium text-muted">Business date</label>
                                <input id="create-queue-date" name="business_date" type="date" value="{{ old('business_date', $businessDate) }}" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                            </div>

                            <x-dashboard.button type="submit" class="w-full">Create Queue</x-dashboard.button>
                        </form>
                    @endif
                </x-dashboard.card>
            @endif
        </div>
    </div>
@endsection
