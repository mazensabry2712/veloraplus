@php
    $entitlementService = app('App\\Application\\Entitlements\\EntitlementService');
    $tenantContext = app('App\\Domain\\Tenancy\\TenantContext');

    $sections = [
        'Company' => [
            ['label' => 'Profile', 'route' => 'company.profile', 'icon' => 'building'],
            ['label' => 'Locations', 'route' => 'company.locations', 'icon' => 'location'],
            ['label' => 'Staff', 'route' => 'company.staff', 'icon' => 'users'],
            ['label' => 'Customers', 'route' => 'company.customers', 'icon' => 'user'],
            ['label' => 'Users', 'route' => 'company.users', 'icon' => 'users'],
            ['label' => 'Roles & Permissions', 'route' => 'company.roles', 'icon' => 'shield'],
        ],
        'Booking' => [
            ['label' => 'Services', 'route' => 'dashboard.booking.services.index', 'permission' => 'booking.services.view', 'entitlement' => 'booking.services', 'icon' => 'briefcase'],
            ['label' => 'Availability', 'route' => 'dashboard.booking.availability.index', 'permission' => 'booking.availability.view', 'entitlement' => 'booking.availability', 'icon' => 'clock'],
            ['label' => 'Appointments', 'route' => 'dashboard.booking.appointments.index', 'permission' => 'booking.appointments.view', 'entitlement' => 'booking.appointments', 'icon' => 'calendar'],
            ['label' => 'Queue', 'route' => 'dashboard.booking.queues.index', 'permission' => 'booking.queues.view', 'entitlement' => 'booking.queues', 'icon' => 'queue'],
            ['label' => 'Payments', 'route' => null, 'icon' => 'card'],
        ],
        'Platform' => [
            ['label' => 'Subscription', 'route' => null, 'icon' => 'sparkles'],
            ['label' => 'Module Marketplace', 'route' => null, 'icon' => 'grid'],
        ],
    ];
@endphp

<div class="space-y-5">
    <section>
        <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Workspace</h2>

        <x-dashboard.nav-item
            label="Overview"
            :href="route('dashboard')"
            :active="request()->routeIs('dashboard')"
            icon="grid"
        />
    </section>

    @foreach ($sections as $section => $items)
        <section>
            <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{{ $section }}</h2>

            <div class="space-y-1">
                @foreach ($items as $item)
                    @php
                        $permission = $item['permission'] ?? match ($item['route']) {
                            'company.profile' => 'company.view',
                            'company.locations' => 'locations.view',
                            'company.staff' => 'staff.view',
                            'company.customers' => 'customers.view',
                            'company.users', 'company.roles' => 'members.view',
                            default => null,
                        };
                        $entitlement = $item['entitlement'] ?? null;
                    @endphp

                    @if ($item['route'] === null)
                        <x-dashboard.nav-item
                            :label="$item['label']"
                            :icon="$item['icon']"
                            disabled
                        />
                    @elseif (($permission === null || auth()->user()->can($permission)) && ($entitlement === null || $entitlementService->canUse($tenantContext->current(), $entitlement)))
                        <x-dashboard.nav-item
                            :label="$item['label']"
                            :href="route($item['route'])"
                            :active="request()->routeIs($item['route'])"
                            :icon="$item['icon']"
                        />
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach

    <section>
        <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Administration</h2>

        <div class="space-y-1">
            <x-dashboard.nav-item
                label="Usage & Limits"
                :href="route('dashboard.usage')"
                :active="request()->routeIs('dashboard.usage')"
                icon="chart"
            />

            <x-dashboard.nav-item
                label="Settings"
                :href="route('dashboard.settings')"
                :active="request()->routeIs('dashboard.settings')"
                icon="settings"
            />
        </div>
    </section>
</div>
