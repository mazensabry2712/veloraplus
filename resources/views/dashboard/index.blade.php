@extends('layouts.dashboard')

@section('title', 'Overview')
@section('heading', 'Overview')

@section('content')
    <x-dashboard.page-header
        title="Overview"
        description="A fast operational view of your company workspace. Detailed workspaces appear as their backend contracts are exposed through the Dashboard."
    />

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard.card title="Company">
            <p class="text-xl font-semibold tracking-tight text-secondary">{{ $tenant->name }}</p>
            <p class="mt-1 text-sm text-muted">{{ $tenant->slug }}.velora.test</p>
        </x-dashboard.card>

        <x-dashboard.card title="Your role">
            <p class="text-xl font-semibold tracking-tight text-secondary">{{ ucfirst($membership->role_key) }}</p>
            <p class="mt-1 text-sm text-muted">Tenant-scoped access</p>
        </x-dashboard.card>

        <x-dashboard.card title="Booking">
            <p class="text-xl font-semibold tracking-tight text-secondary">Available</p>
            <p class="mt-1 text-sm text-muted">Booking contracts are implemented in the backend.</p>
        </x-dashboard.card>

        <x-dashboard.card title="Platform">
            <p class="text-xl font-semibold tracking-tight text-secondary">Connected</p>
            <p class="mt-1 text-sm text-muted">Core, billing, payments and entitlements are active.</p>
        </x-dashboard.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
        <x-dashboard.card title="Workspace architecture" description="The Dashboard consumes backend contracts instead of recreating business rules in Blade or JavaScript.">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['label' => 'Tenant isolation', 'state' => 'Active'],
                    ['label' => 'Tenant membership', 'state' => 'Active'],
                    ['label' => 'RBAC / policies', 'state' => 'Active'],
                    ['label' => 'Entitlements', 'state' => 'Active'],
                ] as $item)
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-border bg-surface px-4 py-3">
                        <span class="text-sm font-medium text-secondary">{{ $item['label'] }}</span>
                        <x-dashboard.badge variant="success">{{ $item['state'] }}</x-dashboard.badge>
                    </div>
                @endforeach
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="Delivery principle" description="Keep the UI fast and predictable as the number of tenants and records grows.">
            <ul class="space-y-3 text-sm leading-6 text-muted">
                <li>Paginate operational lists.</li>
                <li>Prefer aggregates over loading full collections.</li>
                <li>Keep tenant-aware boundaries at the backend.</li>
                <li>Use shared components instead of page-specific UI duplication.</li>
            </ul>
        </x-dashboard.card>
    </div>
@endsection
