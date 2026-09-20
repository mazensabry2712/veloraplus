@extends('layouts.dashboard')

@section('title', __('dashboard.overview'))
@section('heading', __('dashboard.overview'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.overview')"
        :description="__('dashboard.overview_description')"
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="hidden rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-secondary sm:inline-flex">
                    {{ $summary['today'] }}
                </span>

                @if (auth()->user()->can('company.view'))
                    <a href="{{ route('company.profile') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        {{ __('dashboard.company_profile') }}
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
                            {{ __('dashboard.roles.'.$membership->role_key) }} {{ __('dashboard.access') }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-slate-200 ring-1 ring-inset ring-white/10">
                            {{ request()->getHost() }}
                        </span>
                    </div>

                    <h2 class="mt-5 max-w-3xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        {{ $tenant->name }}
                    </h2>

                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base">
                        {{ __('dashboard.command_center_description') }}
                    </p>
                </div>

                <div class="mt-8 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">{{ __('dashboard.appointments_today') }}</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $summary['metrics']['appointments_today'] }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">{{ __('dashboard.open_queues') }}</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $summary['metrics']['open_queues'] }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">{{ __('dashboard.waiting_now') }}</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $summary['metrics']['waiting_queue_entries'] }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-300">{{ __('dashboard.workspace_health') }}</p>
                        <p class="mt-1 text-sm text-white">{{ __('dashboard.core_controls_connected') }}</p>
                    </div>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-accent" aria-hidden="true">✓</span>
                </div>

                <div class="mt-5 divide-y divide-white/10">
                    @foreach ([
                        ['label' => __('dashboard.tenant_isolation'), 'detail' => __('dashboard.tenant_scoped_data')],
                        ['label' => __('dashboard.rbac_policies'), 'detail' => __('dashboard.backend_authorization')],
                        ['label' => __('dashboard.entitlements'), 'detail' => __('dashboard.module_capability_state')],
                    ] as $health)
                        <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div>
                                <p class="text-sm font-medium text-white">{{ $health['label'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $health['detail'] }}</p>
                            </div>
                            <x-dashboard.badge variant="success">{{ __('dashboard.active') }}</x-dashboard.badge>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-primary">{{ __('dashboard.today') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-secondary">{{ __('dashboard.operational_summary') }}</h2>
            </div>
            <p class="hidden text-sm text-muted sm:block">{{ $summary['timezone'] }}</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.stat
                :label="__('dashboard.appointments')"
                :value="$summary['metrics']['appointments_today']"
                :detail="$summary['metrics']['confirmed_today'].' '.__('dashboard.confirmed_short').' · '.$summary['metrics']['pending_today'].' '.__('dashboard.pending_short')"
                icon="calendar"
                :href="route('dashboard.booking.appointments.index')"
            />

            <x-dashboard.stat
                :label="__('dashboard.active_customers')
                :value="$summary['metrics']['active_customers']"
                :detail="__('dashboard.customer_records_ready')"
                icon="users"
                :href="route('company.customers')"
            />

            <x-dashboard.stat
                :label="__('dashboard.active_staff')"
                :value="$summary['metrics']['active_staff']"
                :detail="$summary['metrics']['active_locations'].' '.__('dashboard.active_locations_detail')"
                icon="user"
                :href="route('company.staff')"
            />

            <x-dashboard.stat
                :label="__('dashboard.collected_today')"
                :value="number_format($summary['metrics']['collected_today_minor'] / 100, 2).' '.$summary['currency']"
                :detail="__('dashboard.successful_tenant_payments')"
                icon="card"
            />
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,1fr)]">
        <x-dashboard.card
            :title="__('dashboard.upcoming_appointments')"
            :description="__('dashboard.upcoming_description')"
        >
            <x-slot:header>
                <a href="{{ route('dashboard.booking.appointments.index') }}" class="text-sm font-semibold text-primary hover:text-primary-dark">{{ __('dashboard.view_all') }}</a>
            </x-slot:header>

            @if ($summary['upcoming_appointments']->isEmpty())
                <div class="rounded-xl border border-dashed border-border bg-surface px-5 py-8 text-center">
                    <p class="text-sm font-semibold text-secondary">{{ __('dashboard.no_upcoming') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('dashboard.new_bookings_here') }}</p>
                    <a href="{{ route('dashboard.booking.appointments.index') }}" class="mt-4 inline-flex text-sm font-semibold text-primary hover:text-primary-dark">{{ __('dashboard.open_appointments') }} →</a>
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

        <x-dashboard.card  :title="__('dashboard.todays_queue')" :description="__('dashboard.live_queue_signals')">
            <div class="grid grid-cols-3 divide-x divide-border rounded-xl border border-border bg-surface" dir="ltr">
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">{{ __('dashboard.open') }}</p>
                    <p class="mt-1 text-xl font-semibold text-secondary">{{ $summary['metrics']['open_queues'] }}</p>
                </div>
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">{{ __('dashboard.waiting') }}</p>
                    <p class="mt-1 text-xl font-semibold text-secondary">{{ $summary['metrics']['waiting_queue_entries'] }}</p>
                </div>
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">{{ __('dashboard.serving') }}</p>
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
                            <p class="text-sm font-semibold text-secondary">{{ $queue->waiting_count }} {{ __('dashboard.waiting') }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ __('dashboard.'.($queue->status?->value ?? 'closed')) }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-border bg-surface px-4 py-7 text-center">
                        <p class="text-sm font-semibold text-secondary">{{ __('dashboard.no_queues_today') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('dashboard.no_queues_detail') }}</p>
                    </div>
                @endforelse
            </div>

            <a href="{{ route('dashboard.booking.queues.index') }}" class="mt-4 inline-flex text-sm font-semibold text-primary hover:text-primary-dark">
                {{ __('dashboard.open_queue_workspace') }} →
            </a>
        </x-dashboard.card>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,1fr)]">
        <x-dashboard.card  :title="__('dashboard.recent_activity')" :description="__('dashboard.recent_description')">
            @if ($summary['recent_activity']->isEmpty())
                <div class="rounded-xl border border-dashed border-border bg-surface px-5 py-8 text-center">
                    <p class="text-sm font-semibold text-secondary">{{ __('dashboard.no_activity') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('dashboard.activity_hint') }}</p>
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
                                        {{ match ($activity->to_status) {
                                            'confirmed' => __('dashboard.confirmed'),
                                            'pending' => __('dashboard.pending'),
                                            'completed' => __('dashboard.completed', []),
                                            default => str($activity->to_status)->replace('_', ' ')->title(),
                                        } }}
                                        <span class="font-normal text-muted">· {{ $activity->appointment?->customer?->name ?? 'Customer' }}</span>
                                    </p>
                                    <span class="text-xs text-muted">{{ $activity->changed_at?->timezone($summary['timezone'])->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm leading-6 text-muted">
                                    {{ $activity->reason ?: __('dashboard.status_updated') }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-dashboard.card>

        <x-dashboard.card  :title="__('dashboard.workspace_capacity')" :description="__('dashboard.capacity_description')">
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
                <p class="text-xs font-semibold uppercase tracking-[0.11em] text-primary">{{ __('dashboard.available_workspaces') }}</p>
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
