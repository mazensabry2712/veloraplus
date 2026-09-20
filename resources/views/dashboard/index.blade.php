@extends('layouts.dashboard')

@section('title', 'Overview')
@section('heading', 'Overview')

@section('content')
    <x-dashboard.page-header
        title="Overview"
        description="A live operational view of your company workspace, with today's booking signals and the work areas that are available to you."
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="hidden rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-secondary sm:inline-flex">
                    {{ $summary['today'] }}
                </span>

                @if (auth()->user()->can('company.view'))
                    <a href="{{ route('company.profile') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        Company Profile
                    </a>
                @endif
            </div>
        </x-slot:actions>
    </x-dashboard.page-header>

    <section class="mt-6 overflow-hidden rounded-2xl bg-secondary shadow-sm">
        <div class="grid gap-7 px-6 py-7 sm:px-8 sm:py-8 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-stretch">
            <div class="flex min-h-64 flex-col justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white ring-1 ring-inset ring-white/15">
                            <span class="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden="true"></span>
                            {{ ucfirst($membership->role_key) }} access
                        </span>
                        <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-slate-200 ring-1 ring-inset ring-white/10">
                            {{ request()->getHost() }}
                        </span>
                    </div>

                    <h2 class="mt-5 max-w-3xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        {{ $tenant->name }}
                    </h2>

                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base">
                        Your operational command center for bookings, customers, staff, locations, and the capabilities enabled for this tenant.
                    </p>
                </div>

                <div class="mt-8 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">Appointments today</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $summary['metrics']['appointments_today'] }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">Open queues</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $summary['metrics']['open_queues'] }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">Waiting now</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $summary['metrics']['waiting_queue_entries'] }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-300">Workspace health</p>
                        <p class="mt-1 text-sm text-white">Core controls are connected.</p>
                    </div>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-accent" aria-hidden="true">✓</span>
                </div>

                <div class="mt-5 divide-y divide-white/10">
                    @foreach ([
                        ['label' => 'Tenant isolation', 'detail' => 'Tenant-scoped data access'],
                        ['label' => 'RBAC & policies', 'detail' => 'Backend authorization'],
                        ['label' => 'Entitlements', 'detail' => 'Module capability state'],
                    ] as $health)
                        <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div>
                                <p class="text-sm font-medium text-white">{{ $health['label'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $health['detail'] }}</p>
                            </div>
                            <x-dashboard.badge variant="success">Active</x-dashboard.badge>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-primary">Today</p>
                <h2 class="mt-1 text-lg font-semibold text-secondary">Operational summary</h2>
            </div>
            <p class="hidden text-sm text-muted sm:block">{{ $summary['timezone'] }}</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.stat
                label="Appointments"
                :value="$summary['metrics']['appointments_today']"
                :detail="$summary['metrics']['confirmed_today'].' confirmed · '.$summary['metrics']['pending_today'].' pending'"
                icon="calendar"
                :href="route('dashboard.booking.appointments.index')"
            />

            <x-dashboard.stat
                label="Active customers"
                :value="$summary['metrics']['active_customers']"
                detail="Customer records ready for operations"
                icon="users"
                :href="route('company.customers')"
            />

            <x-dashboard.stat
                label="Active staff"
                :value="$summary['metrics']['active_staff']"
                :detail="$summary['metrics']['active_locations'].' active locations'"
                icon="user"
                :href="route('company.staff')"
            />

            <x-dashboard.stat
                label="Collected today"
                :value="number_format($summary['metrics']['collected_today_minor'] / 100, 2).' '.$summary['currency']"
                detail="Successful tenant payments"
                icon="card"
            />
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,1fr)]">
        <x-dashboard.card
            title="Upcoming appointments"
            description="The next confirmed and pending bookings in this tenant."
        >
            <x-slot:header>
                <a href="{{ route('dashboard.booking.appointments.index') }}" class="text-sm font-semibold text-primary hover:text-primary-dark">View all</a>
            </x-slot:header>

            @if ($summary['upcoming_appointments']->isEmpty())
                <div class="rounded-xl border border-dashed border-border bg-surface px-5 py-8 text-center">
                    <p class="text-sm font-semibold text-secondary">No upcoming appointments</p>
                    <p class="mt-1 text-sm text-muted">New bookings will appear here automatically.</p>
                    <a href="{{ route('dashboard.booking.appointments.index') }}" class="mt-4 inline-flex text-sm font-semibold text-primary hover:text-primary-dark">Open appointments →</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <div class="min-w-[42rem] divide-y divide-border">
                        @foreach ($summary['upcoming_appointments'] as $appointment)
                            @php
                                $service = $appointment->items->first()?->service_name ?? 'Service';
                            @endphp
                            <div class="flex items-center gap-4 py-4 first:pt-0 last:pb-0">
                                <div class="w-20 shrink-0">
                                    <p class="text-sm font-semibold text-secondary">{{ $appointment->starts_at->timezone($summary['timezone'])->format('g:i') }}</p>
                                    <p class="mt-0.5 text-xs text-muted">{{ $appointment->starts_at->timezone($summary['timezone'])->format('A') }}</p>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-secondary">{{ $appointment->customer?->name ?? 'Customer' }}</p>
                                    <p class="mt-0.5 truncate text-sm text-muted">{{ $service }} · {{ $appointment->staff?->name ?? 'Staff' }}</p>
                                </div>

                                <div class="hidden min-w-28 text-end sm:block">
                                    <p class="truncate text-xs font-medium text-secondary">{{ $appointment->location?->name ?? 'Location' }}</p>
                                    <p class="mt-0.5 text-xs text-muted">{{ $appointment->starts_at->timezone($summary['timezone'])->format('M j') }}</p>
                                </div>

                                <x-dashboard.badge :variant="$appointment->status?->value === 'confirmed' ? 'success' : 'warning'">
                                    {{ ucfirst($appointment->status?->value ?? 'pending') }}
                                </x-dashboard.badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-dashboard.card>

        <x-dashboard.card title="Today's queue" description="Live signals from queues operating on the current business date.">
            <div class="grid grid-cols-3 divide-x divide-border rounded-xl border border-border bg-surface" dir="ltr">
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">Open</p>
                    <p class="mt-1 text-xl font-semibold text-secondary">{{ $summary['metrics']['open_queues'] }}</p>
                </div>
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">Waiting</p>
                    <p class="mt-1 text-xl font-semibold text-secondary">{{ $summary['metrics']['waiting_queue_entries'] }}</p>
                </div>
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">Serving</p>
                    <p class="mt-1 text-xl font-semibold text-secondary">{{ $summary['metrics']['serving_queue_entries'] }}</p>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($summary['queues'] as $queue)
                    <div class="flex items-center gap-3 rounded-xl border border-border bg-white px-4 py-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                            @include('components.dashboard.icon', ['name' => 'queue'])
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-secondary">{{ $queue->service?->name ?? 'Service' }}</p>
                            <p class="mt-0.5 truncate text-xs text-muted">{{ $queue->location?->name ?? 'Location' }}</p>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-semibold text-secondary">{{ $queue->waiting_count }} waiting</p>
                            <p class="mt-0.5 text-xs text-muted">{{ ucfirst($queue->status?->value ?? 'closed') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-border bg-surface px-4 py-7 text-center">
                        <p class="text-sm font-semibold text-secondary">No queues today</p>
                        <p class="mt-1 text-sm text-muted">Create a queue when you are ready for walk-in operations.</p>
                    </div>
                @endforelse
            </div>

            <a href="{{ route('dashboard.booking.queues.index') }}" class="mt-4 inline-flex text-sm font-semibold text-primary hover:text-primary-dark">
                Open queue workspace →
            </a>
        </x-dashboard.card>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,1fr)]">
        <x-dashboard.card title="Recent activity" description="The latest appointment lifecycle events recorded by the backend.">
            @if ($summary['recent_activity']->isEmpty())
                <div class="rounded-xl border border-dashed border-border bg-surface px-5 py-8 text-center">
                    <p class="text-sm font-semibold text-secondary">No activity yet</p>
                    <p class="mt-1 text-sm text-muted">Appointment lifecycle changes will appear here.</p>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($summary['recent_activity'] as $activity)
                        <div class="flex gap-3">
                            <div class="flex w-7 shrink-0 flex-col items-center">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                                    @include('components.dashboard.icon', ['name' => 'chart'])
                                </span>
                                @if (! $loop->last)
                                    <span class="mt-2 h-full w-px bg-border" aria-hidden="true"></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-secondary">
                                        {{ ucfirst(str_replace('_', ' ', $activity->to_status)) }}
                                        <span class="font-normal text-muted">· {{ $activity->appointment?->customer?->name ?? 'Customer' }}</span>
                                    </p>
                                    <span class="text-xs text-muted">{{ $activity->changed_at?->timezone($summary['timezone'])->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm leading-6 text-muted">
                                    {{ $activity->reason ?: 'Appointment status updated.' }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-dashboard.card>

        <x-dashboard.card title="Workspace capacity" description="The active operational footprint of this tenant.">
            <div class="space-y-3">
                @foreach ([
                    ['label' => 'Services', 'value' => $summary['metrics']['active_services'], 'href' => route('dashboard.booking.services.index')],
                    ['label' => 'Staff', 'value' => $summary['metrics']['active_staff'], 'href' => route('company.staff')],
                    ['label' => 'Locations', 'value' => $summary['metrics']['active_locations'], 'href' => route('company.locations')],
                ] as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center justify-between gap-4 rounded-xl border border-border bg-white px-4 py-3 transition-colors hover:border-primary/20 hover:bg-primary/5">
                        <span class="text-sm font-medium text-secondary">{{ $item['label'] }}</span>
                        <span class="text-sm font-semibold text-primary">{{ $item['value'] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-5 rounded-xl border border-primary/10 bg-primary/5 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.11em] text-primary">Available workspaces</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($quickLinks as $link)
                        <a href="{{ route($link['route']) }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-secondary transition-colors hover:bg-white hover:text-primary">
                            <span class="text-primary" aria-hidden="true">
                                @include('components.dashboard.icon', ['name' => $link['icon']])
                            </span>
                            <span class="truncate">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </x-dashboard.card>
    </section>
@endsection
