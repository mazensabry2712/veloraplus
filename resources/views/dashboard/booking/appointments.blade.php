@extends('layouts.dashboard')

@section('title', 'Appointments')
@section('heading', 'Appointments')

@section('content')
    @php
        use App\Domain\Booking\AppointmentStatus;

        $statusOptions = collect(AppointmentStatus::cases())
            ->mapWithKeys(fn (AppointmentStatus $status): array => [
                $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    @endphp

    <x-dashboard.page-header
        title="Appointments"
        description="Schedule, review, and manage appointments using the existing Booking rules."
    />

    <div class="mt-6 space-y-6">
        <x-dashboard.card>
            <form method="GET" action="{{ route('dashboard.booking.appointments.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1.7fr)_12rem_12rem_auto] lg:items-end">
                <div>
                    <label for="appointment-search" class="mb-1 block text-xs font-medium text-muted">Search</label>
                    <input
                        id="appointment-search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Customer, staff, or service"
                        class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                    >
                </div>

                <div>
                    <label for="appointment-date" class="mb-1 block text-xs font-medium text-muted">Date</label>
                    <input
                        id="appointment-date"
                        name="date"
                        type="date"
                        value="{{ request('date') }}"
                        class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                    >
                </div>

                <div>
                    <label for="appointment-status" class="mb-1 block text-xs font-medium text-muted">Status</label>
                    <select id="appointment-status" name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <x-dashboard.button type="submit" size="sm">Filter</x-dashboard.button>
                    @if (request()->hasAny(['q', 'date', 'status']))
                        <a href="{{ route('dashboard.booking.appointments.index') }}" class="inline-flex min-h-9 items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary hover:bg-surface">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </x-dashboard.card>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <x-dashboard.card title="Appointment List" description="Tenant-scoped appointments, sorted by start time.">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-primary/5">
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                            <th class="px-4 py-3">Appointment</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payment</th>
                            @if ($canManage)
                                <th class="px-4 py-3 text-end">Actions</th>
                            @endif
                        </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                        @forelse ($appointments as $appointment)
                            @php
                                $status = $appointment->status instanceof AppointmentStatus
                                    ? $appointment->status->value
                                    : (string) $appointment->status;
                                $paymentStatus = $appointment->payment_status instanceof \BackedEnum
                                    ? $appointment->payment_status->value
                                    : (string) $appointment->payment_status;
                                $item = $appointment->items->first();
                                $statusVariant = match ($status) {
                                    'confirmed' => 'success',
                                    'pending' => 'warning',
                                    'completed' => 'info',
                                    'cancelled', 'no_show' => 'danger',
                                    default => 'neutral',
                                };
                                $paymentVariant = match ($paymentStatus) {
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'failed', 'refunded' => 'danger',
                                    default => 'neutral',
                                };
                                $localStart = $appointment->starts_at->setTimezone($timezone);
                                $localEnd = $appointment->ends_at->setTimezone($timezone);
                            @endphp

                            <tr class="align-top transition-colors hover:bg-primary/5">
                                <td class="px-4 py-4">
                                    <div class="font-medium text-secondary">{{ $item?->service_name ?? 'Appointment' }}</div>
                                    <div class="mt-1 whitespace-nowrap text-sm text-muted">
                                        {{ $localStart->format('d M Y, H:i') }} – {{ $localEnd->format('H:i') }}
                                    </div>
                                    @if ($item)
                                        <div class="mt-1 text-xs text-muted">
                                            {{ number_format($item->line_total_minor / 100, 2) }} {{ $item->currency }}
                                            @if ($item->quantity > 1)
                                                · Qty {{ $item->quantity }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-secondary">{{ $appointment->customer?->name ?? '—' }}</div>
                                    @if ($appointment->customer?->phone)
                                        <div class="mt-1 text-xs text-muted">{{ $appointment->customer->phone }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-muted">{{ $appointment->staff?->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-muted">
                                    {{ $appointment->location?->name ?? '—' }}
                                    @if ($appointment->location?->timezone)
                                        <div class="mt-1 text-xs">{{ $appointment->location->timezone }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <x-dashboard.badge :variant="$statusVariant">
                                        {{ $statusOptions[$status] ?? str($status)->replace('_', ' ')->title() }}
                                    </x-dashboard.badge>
                                    @if ($status === 'cancelled' && $appointment->cancellation_reason)
                                        <div class="mt-2 max-w-xs text-xs leading-5 text-muted">{{ $appointment->cancellation_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <x-dashboard.badge :variant="$paymentVariant">
                                        {{ $paymentStatus === '' ? 'Unknown' : str($paymentStatus)->replace('_', ' ')->title() }}
                                    </x-dashboard.badge>
                                </td>

                                @if ($canManage)
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if (in_array($status, ['pending', 'confirmed'], true))
                                                <details>
                                                    <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">
                                                        Reschedule
                                                    </summary>
                                                    <form method="POST" action="{{ route('dashboard.booking.appointments.reschedule', $appointment) }}" class="mt-3 w-[min(24rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="mb-1 block text-xs font-medium text-muted">New start time</label>
                                                        <input
                                                            name="starts_at"
                                                            value="{{ $localStart->format('Y-m-d\TH:i:sP') }}"
                                                            required
                                                            class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                                                        >
                                                        <p class="mt-2 text-[11px] leading-5 text-muted">Use an ISO-8601 value with the tenant timezone, for example 2026-09-21T14:00:00+03:00.</p>
                                                        <div class="mt-3 flex justify-end">
                                                            <x-dashboard.button type="submit" size="sm">Save Time</x-dashboard.button>
                                                        </div>
                                                    </form>
                                                </details>
                                            @endif

                                            @if ($status === 'pending')
                                                <form method="POST" action="{{ route('dashboard.booking.appointments.confirm', $appointment) }}">
                                                    @csrf
                                                    <x-dashboard.button type="submit" size="sm">Confirm</x-dashboard.button>
                                                </form>
                                            @endif

                                            @if ($status === 'confirmed')
                                                <form method="POST" action="{{ route('dashboard.booking.appointments.complete', $appointment) }}">
                                                    @csrf
                                                    <x-dashboard.button type="submit" size="sm">Complete</x-dashboard.button>
                                                </form>

                                                <form method="POST" action="{{ route('dashboard.booking.appointments.no-show', $appointment) }}">
                                                    @csrf
                                                    <x-dashboard.button variant="danger" type="submit" size="sm">No-show</x-dashboard.button>
                                                </form>

                                                <details>
                                                    <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-red-200 px-3 text-xs font-medium text-red-700 [&::-webkit-details-marker]:hidden">
                                                        Cancel
                                                    </summary>
                                                    <form method="POST" action="{{ route('dashboard.booking.appointments.cancel', $appointment) }}" class="mt-3 w-[min(24rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                        @csrf
                                                        <label class="mb-1 block text-xs font-medium text-muted">Reason</label>
                                                        <input name="reason" maxlength="255" placeholder="Customer request" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                        <div class="mt-3 flex justify-end">
                                                            <x-dashboard.button variant="danger" type="submit" size="sm">Cancel Appointment</x-dashboard.button>
                                                        </div>
                                                    </form>
                                                </details>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 7 : 6 }}" class="px-4 py-12 text-center text-muted">
                                    No appointments match the current filters.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <x-dashboard.pagination :paginator="$appointments" />
            </x-dashboard.card>

            @if ($canManage)
                <x-dashboard.card title="Create Appointment" description="Create through AppointmentManager so availability, conflicts, and idempotency remain authoritative.">
                    @if ($customers->isEmpty() || $staffMembers->isEmpty() || $services->isEmpty())
                        <div class="rounded-xl border border-dashed border-border bg-surface p-5 text-sm leading-6 text-muted">
                            Active customers, staff, and services are required before creating an appointment.
                        </div>
                    @else
                        <form method="POST" action="{{ route('dashboard.booking.appointments.store') }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="appointment-customer" class="mb-1 block text-xs font-medium text-muted">Customer</label>
                                <select id="appointment-customer" name="customer_id" required class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                    <option value="">Select customer</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->getKey() }}" @selected(old('customer_id') === (string) $customer->getKey())>
                                            {{ $customer->name }}@if($customer->phone) — {{ $customer->phone }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="appointment-staff" class="mb-1 block text-xs font-medium text-muted">Staff</label>
                                <select id="appointment-staff" name="staff_id" required class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                    <option value="">Select staff</option>
                                    @foreach ($staffMembers as $staffMember)
                                        <option value="{{ $staffMember->getKey() }}" @selected(old('staff_id') === (string) $staffMember->getKey())>
                                            {{ $staffMember->name }}@if($staffMember->location) — {{ $staffMember->location->name }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="appointment-service" class="mb-1 block text-xs font-medium text-muted">Service</label>
                                <select id="appointment-service" name="service_id" required class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                    <option value="">Select service</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->getKey() }}" @selected(old('service_id') === (string) $service->getKey())>
                                            {{ $service->name }} · {{ $service->duration_minutes }} min · {{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="appointment-starts-at" class="mb-1 block text-xs font-medium text-muted">Start time</label>
                                <input
                                    id="appointment-starts-at"
                                    name="starts_at"
                                    value="{{ old('starts_at', now($timezone)->format('Y-m-d\TH:i:sP')) }}"
                                    required
                                    placeholder="2026-09-21T10:00:00+03:00"
                                    class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                                >
                                <p class="mt-1 text-[11px] leading-5 text-muted">Use the tenant timezone in the ISO-8601 value.</p>
                            </div>

                            <div>
                                <label for="appointment-idempotency-key" class="mb-1 block text-xs font-medium text-muted">Idempotency key <span class="font-normal">(optional)</span></label>
                                <input
                                    id="appointment-idempotency-key"
                                    name="idempotency_key"
                                    value="{{ old('idempotency_key') }}"
                                    maxlength="190"
                                    placeholder="booking-2026-0001"
                                    class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"
                                >
                            </div>

                            <div>
                                <label for="appointment-notes" class="mb-1 block text-xs font-medium text-muted">Notes <span class="font-normal">(optional)</span></label>
                                <textarea id="appointment-notes" name="notes" rows="4" maxlength="5000" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ old('notes') }}</textarea>
                            </div>

                            <x-dashboard.button type="submit" class="w-full">Create Appointment</x-dashboard.button>
                        </form>
                    @endif
                </x-dashboard.card>
            @endif
        </div>

        @if ($canManage)
            <p class="text-xs leading-5 text-muted">
                The create form shows active tenant records. A service must already be assigned to the selected staff member; the existing AppointmentManager remains the final validation boundary.
            </p>
        @endif
    </div>
@endsection
