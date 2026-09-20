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

    <section class="mt-8 overflow-hidden rounded-3xl bg-secondary shadow-sm">
        <div class="grid gap-8 px-6 py-7 sm:px-8 sm:py-9 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white ring-1 ring-inset ring-white/10">
                        <span class="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden="true"></span>
                        {{ __('dashboard.roles.'.$membership->role_key) }} · {{ $tenant->name }}
                    </span>
                    <span class="text-xs font-medium text-slate-400">{{ request()->getHost() }}</span>
                </div>

                <h2 class="mt-6 max-w-3xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    {{ __('dashboard.welcome_back') }}, {{ auth()->user()->name }}
                </h2>

                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    {{ __('dashboard.command_center_description') }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('dashboard.today') }}</p>
                <p class="mt-2 text-lg font-semibold text-white">{{ $summary['today'] }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-400">{{ $summary['timezone'] }}</p>
                <a href="{{ route('dashboard.booking.appointments.index') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-white transition-colors hover:text-blue-200">
                    {{ __('dashboard.open_appointments') }}
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="dashboard-eyebrow">{{ __('dashboard.today') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-secondary">{{ __('dashboard.operational_summary') }}</h2>
            </div>
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
                :label="__('dashboard.active_customers')"
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

    <section class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,0.75fr)]">
        <x-dashboard.card
            :title="__('dashboard.upcoming_appointments')"
            :description="__('dashboard.upcoming_description')"
        >
            <x-slot:header>
                <a href="{{ route('dashboard.booking.appointments.index') }}" class="text-sm font-semibold text-primary hover:text-primary-dark">
                    {{ __('dashboard.view_all') }}
                </a>
            </x-slot:header>

            @if ($summary['upcoming_appointments']->isEmpty())
                <div class="rounded-xl border border-dashed border-border bg-surface px-5 py-9 text-center">
                    <p class="text-sm font-semibold text-secondary">{{ __('dashboard.no_upcoming') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('dashboard.new_bookings_here') }}</p>
                </div>
            @else
                <div class="divide-y divide-border">
                    @foreach ($summary['upcoming_appointments'] as $appointment)
                        @php
                            $service = $appointment->items->first()?->service_name ?? 'Service';
                        @endphp

                        <a href="{{ route('dashboard.booking.appointments.index', ['q' => $appointment->customer?->name]) }}" class="group flex items-center gap-4 py-4 first:pt-0 last:pb-0">
                            <div class="w-20 shrink-0 rounded-xl bg-surface px-3 py-2">
                                <p class="text-sm font-semibold text-secondary">{{ $appointment->starts_at->timezone($summary['timezone'])->format('g:i') }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $appointment->starts_at->timezone($summary['timezone'])->format('A') }}</p>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-secondary group-hover:text-primary">{{ $appointment->customer?->name ?? 'Customer' }}</p>
                                <p class="mt-1 truncate text-sm text-muted">{{ $service }} · {{ $appointment->staff?->name ?? 'Staff' }}</p>
                            </div>

                            <div class="hidden min-w-28 text-end sm:block">
                                <p class="truncate text-xs font-medium text-secondary">{{ $appointment->location?->name ?? 'Location' }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $appointment->starts_at->timezone($summary['timezone'])->format('M j') }}</p>
                            </div>

                            <x-dashboard.badge :variant="$appointment->status?->value === 'confirmed' ? 'success' : 'warning'">
                                {{ match ($appointment->status?->value) {
                                    'confirmed' => __('dashboard.confirmed'),
                                    'pending' => __('dashboard.pending'),
                                    default => str($appointment->status?->value ?? 'pending')->replace('_', ' ')->title(),
                                } }}
                            </x-dashboard.badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-dashboard.card>

        <x-dashboard.card :title="__('dashboard.todays_queue')" :description="__('dashboard.live_queue_signals')">
            <div class="grid grid-cols-3 divide-x divide-border rounded-xl border border-border bg-surface" dir="ltr">
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">{{ __('dashboard.open') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-secondary">{{ $summary['metrics']['open_queues'] }}</p>
                </div>
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">{{ __('dashboard.waiting') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-secondary">{{ $summary['metrics']['waiting_queue_entries'] }}</p>
                </div>
                <div class="px-3 py-4 text-center">
                    <p class="text-xs font-medium text-muted">{{ __('dashboard.serving') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-secondary">{{ $summary['metrics']['serving_queue_entries'] }}</p>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($summary['queues'] as $queue)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                            @include('components.dashboard.icon', ['name' => 'queue'])
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-secondary">{{ $queue->service?->name ?? 'Service' }}</p>
                            <p class="mt-0.5 truncate text-xs text-muted">{{ $queue->location?->name ?? 'Location' }}</p>
                        </div>
                        <span class="text-xs font-semibold text-secondary">{{ $queue->waiting_count }} {{ __('dashboard.waiting') }}</span>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-border bg-surface px-4 py-8 text-center">
                        <p class="text-sm font-semibold text-secondary">{{ __('dashboard.no_queues_today') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('dashboard.no_queues_detail') }}</p>
                    </div>
                @endforelse
            </div>

            <a href="{{ route('dashboard.booking.queues.index') }}" class="mt-5 inline-flex text-sm font-semibold text-primary hover:text-primary-dark">
                {{ __('dashboard.open_queue_workspace') }} →
            </a>
        </x-dashboard.card>
    </section>

    <section class="mt-8">
        <x-dashboard.card :title="__('dashboard.recent_activity')" :description="__('dashboard.recent_description')">
            @if ($summary['recent_activity']->isEmpty())
                <div class="rounded-xl border border-dashed border-border bg-surface px-5 py-9 text-center">
                    <p class="text-sm font-semibold text-secondary">{{ __('dashboard.no_activity') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('dashboard.activity_hint') }}</p>
                </div>
            @else
                <div class="grid gap-x-10 gap-y-5 md:grid-cols-2">
                    @foreach ($summary['recent_activity'] as $activity)
                        <div class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                                @include('components.dashboard.icon', ['name' => 'chart'])
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-secondary">
                                        {{ match ($activity->to_status) {
                                            'confirmed' => __('dashboard.confirmed'),
                                            'pending' => __('dashboard.pending'),
                                            'completed' => __('dashboard.completed'),
                                            'no_show' => __('dashboard.no_show'),
                                            default => str($activity->to_status)->replace('_', ' ')->title(),
                                        } }}
                                        <span class="font-normal text-muted">· {{ $activity->appointment?->customer?->name ?? 'Customer' }}</span>
                                    </p>
                                    <span class="text-xs text-muted">{{ $activity->changed_at?->timezone($summary['timezone'])->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm leading-6 text-muted">{{ $activity->reason ?: __('dashboard.status_updated') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-dashboard.card>
    </section>
@endsection
