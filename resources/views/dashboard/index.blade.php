@extends('layouts.dashboard')

@section('title', 'Overview')
@section('heading', 'Overview')

@section('content')
    <x-dashboard.page-header
        title="Overview"
        description="Your company workspace at a glance, with direct access to the operational areas already enabled for this tenant."
    >
        <x-slot:actions>
            @if (auth()->user()->can('company.view'))
                <a href="{{ route('company.profile') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    Company Profile
                </a>
            @endif
        </x-slot:actions>
    </x-dashboard.page-header>

    <section class="mt-6 overflow-hidden rounded-2xl bg-secondary shadow-sm">
        <div class="grid gap-8 px-6 py-7 sm:px-8 sm:py-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-center">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white ring-1 ring-inset ring-white/15">
                        {{ ucfirst($membership->role_key) }} access
                    </span>
                    <span class="inline-flex items-center rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-secondary">
                        {{ request()->getHost() }}
                    </span>
                </div>

                <h2 class="mt-4 max-w-2xl text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                    Welcome to {{ $tenant->name }}
                </h2>

                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200">
                    Run your company from one tenant-aware workspace. Booking, company operations, permissions, and platform capabilities stay connected through the same backend contracts.
                </p>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-300">Workspace health</p>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Tenant isolation</span>
                        <x-dashboard.badge variant="success">Active</x-dashboard.badge>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">RBAC & policies</span>
                        <x-dashboard.badge variant="success">Active</x-dashboard.badge>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Entitlements</span>
                        <x-dashboard.badge variant="success">Active</x-dashboard.badge>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-primary">Workspace</p>
                <h2 class="mt-1 text-lg font-semibold text-secondary">Quick access</h2>
            </div>
            <p class="hidden text-sm text-muted sm:block">Jump directly into the areas you use most.</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $quickLinks = [
                    [
                        'label' => 'Services',
                        'description' => 'Manage booking services, pricing, and lifecycle.',
                        'route' => 'dashboard.booking.services.index',
                        'permission' => 'booking.services.view',
                        'entitlement' => 'booking.services',
                    ],
                    [
                        'label' => 'Availability',
                        'description' => 'Review Staff hours, breaks, and time off.',
                        'route' => 'dashboard.booking.availability.index',
                        'permission' => 'booking.availability.view',
                        'entitlement' => 'booking.availability',
                    ],
                    [
                        'label' => 'Appointments',
                        'description' => 'Search and manage the booking schedule.',
                        'route' => 'dashboard.booking.appointments.index',
                        'permission' => 'booking.appointments.view',
                        'entitlement' => 'booking.appointments',
                    ],
                    [
                        'label' => 'Queue',
                        'description' => 'Operate the daily customer waiting queue.',
                        'route' => 'dashboard.booking.queues.index',
                        'permission' => 'booking.queues.view',
                        'entitlement' => 'booking.queues',
                    ],
                ];
            @endphp

            @foreach ($quickLinks as $link)
                @if (auth()->user()->can($link['permission']) && app(AppApplicationEntitlementsEntitlementService::class)->canUse(app(AppDomainTenancyTenantContext::class)->current(), $link['entitlement']))
                    <a href="{{ route($link['route']) }}" class="group rounded-2xl border border-border bg-white p-5 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <div class="flex items-start justify-between gap-4">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-inset ring-primary/10" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                                </svg>
                            </span>
                            <span class="text-sm font-semibold text-primary transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                        </div>
                        <h3 class="mt-5 text-base font-semibold text-secondary">{{ $link['label'] }}</h3>
                        <p class="mt-1.5 text-sm leading-6 text-muted">{{ $link['description'] }}</p>
                    </a>
                @endif
            @endforeach
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,1fr)]">
        <x-dashboard.card title="Platform foundation" description="The Dashboard consumes the same tenant-scoped services and policies that power the backend.">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['label' => 'Tenant isolation', 'detail' => 'Company data stays inside the active tenant boundary.'],
                    ['label' => 'Tenant membership', 'detail' => 'Access is scoped to an active company membership.'],
                    ['label' => 'RBAC / policies', 'detail' => 'Backend authorization remains authoritative.'],
                    ['label' => 'Entitlements', 'detail' => 'Module access follows the tenant capability state.'],
                ] as $item)
                    <div class="rounded-xl border border-border bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-secondary">{{ $item['label'] }}</p>
                            <x-dashboard.badge variant="success">Active</x-dashboard.badge>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted">{{ $item['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="How to use this workspace" description="The interface is intentionally optimized for fast operational work.">
            <div class="space-y-4">
                @foreach ([
                    ['step' => '01', 'title' => 'Choose a workspace', 'detail' => 'Use the sidebar or Quick access to reach a module.'],
                    ['step' => '02', 'title' => 'Scan the data', 'detail' => 'Filters, statuses, and compact metadata keep lists easy to review.'],
                    ['step' => '03', 'title' => 'Take the action', 'detail' => 'Primary actions are visually stronger while destructive actions stay explicit.'],
                ] as $item)
                    <div class="flex gap-3">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary">{{ $item['step'] }}</span>
                        <div>
                            <p class="text-sm font-semibold text-secondary">{{ $item['title'] }}</p>
                            <p class="mt-1 text-sm leading-6 text-muted">{{ $item['detail'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-dashboard.card>
    </section>
@endsection
