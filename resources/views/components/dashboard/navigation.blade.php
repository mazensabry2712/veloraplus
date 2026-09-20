@php
    $sections = [
        'Company' => [
            ['label' => 'Profile', 'route' => 'company.profile'],
            ['label' => 'Locations', 'route' => 'company.locations'],
            ['label' => 'Staff', 'route' => 'company.staff'],
            ['label' => 'Customers', 'route' => 'company.customers'],
            ['label' => 'Users', 'route' => 'company.users'],
            ['label' => 'Roles & Permissions', 'route' => 'company.roles'],
        ],
        'Booking' => [
            ['label' => 'Services', 'route' => 'dashboard.booking.services.index', 'permission' => 'booking.services.view', 'entitlement' => 'booking.services'],
            ['label' => 'Availability', 'route' => null],
            ['label' => 'Appointments', 'route' => null],
            ['label' => 'Queue', 'route' => null],
            ['label' => 'Payments', 'route' => null],
        ],
        'Platform' => [
            ['label' => 'Subscription', 'route' => null],
            ['label' => 'Module Marketplace', 'route' => null],
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
                            disabled
                        />
                    @elseif (($permission === null || auth()->user()->can($permission)) && ($entitlement === null || app(\App\Application\Entitlements\EntitlementService::class)->canUse(app(\App\Domain\Tenancy\TenantContext::class)->current(), $entitlement)))
                        <x-dashboard.nav-item
                            :label="$item['label']"
                            :href="route($item['route'])"
                            :active="request()->routeIs($item['route'])"
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
            />

            <x-dashboard.nav-item
                label="Settings"
                :href="route('dashboard.settings')"
                :active="request()->routeIs('dashboard.settings')"
            />
        </div>
    </section>
</div>
