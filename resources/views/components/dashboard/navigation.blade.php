@php
    $entitlementService = app('App\Application\Entitlements\EntitlementService');
    $tenantContext = app('App\Domain\Tenancy\TenantContext');

    $sections = [
        __('dashboard.company') => [
            ['label' => __('dashboard.profile'), 'route' => 'company.profile', 'icon' => 'building'],
            ['label' => __('dashboard.locations'), 'route' => 'company.locations', 'icon' => 'location'],
            ['label' => __('dashboard.staff'), 'route' => 'company.staff', 'icon' => 'users'],
            ['label' => __('dashboard.customers'), 'route' => 'company.customers', 'icon' => 'user'],
            ['label' => __('dashboard.users'), 'route' => 'company.users', 'icon' => 'users'],
            ['label' => __('dashboard.roles_permissions'), 'route' => 'company.roles', 'icon' => 'shield'],
        ],
        __('dashboard.booking') => [
            ['label' => __('dashboard.services'), 'route' => 'dashboard.booking.services.index', 'permission' => 'booking.services.view', 'entitlement' => 'booking.services', 'icon' => 'briefcase'],
            ['label' => __('dashboard.availability'), 'route' => 'dashboard.booking.availability.index', 'permission' => 'booking.availability.view', 'entitlement' => 'booking.availability', 'icon' => 'clock'],
            ['label' => __('dashboard.appointments'), 'route' => 'dashboard.booking.appointments.index', 'permission' => 'booking.appointments.view', 'entitlement' => 'booking.appointments', 'icon' => 'calendar'],
            ['label' => __('dashboard.queue'), 'route' => 'dashboard.booking.queues.index', 'permission' => 'booking.queues.view', 'entitlement' => 'booking.queues', 'icon' => 'queue'],
            ['label' => __('dashboard.payments'), 'route' => null, 'icon' => 'card'],
        ],
        __('dashboard.platform') => [
            ['label' => __('dashboard.subscription'), 'route' => null, 'icon' => 'sparkles'],
            ['label' => __('dashboard.module_marketplace'), 'route' => null, 'icon' => 'grid'],
        ],
    ];
@endphp

<div class="space-y-5">
    <section>
        <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{{ __('dashboard.workspace') }}</h2>

        <x-dashboard.nav-item
            :label="__('dashboard.overview')"
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
        <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{{ __('dashboard.administration') }}</h2>

        <div class="space-y-1">
            @if (auth()->user()->can('usage.view'))
                <x-dashboard.nav-item
                    :label="__('dashboard.usage_limits')"
                    :href="route('dashboard.usage')"
                    :active="request()->routeIs('dashboard.usage')"
                    icon="chart"
                />
            @endif

            @if (auth()->user()->can('settings.view'))
                <x-dashboard.nav-item
                    :label="__('dashboard.settings')"
                    :href="route('dashboard.settings')"
                    :active="request()->routeIs('dashboard.settings')"
                    icon="settings"
                />
            @endif
        </div>
    </section>
</div>
