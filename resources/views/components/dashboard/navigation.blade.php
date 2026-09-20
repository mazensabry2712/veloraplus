@php
    $sections = [
        'Company' => [
            ['label' => 'Profile', 'route' => null],
            ['label' => 'Locations', 'route' => null],
            ['label' => 'Staff', 'route' => null],
            ['label' => 'Customers', 'route' => null],
            ['label' => 'Users', 'route' => null],
            ['label' => 'Roles & Permissions', 'route' => null],
        ],
        'Booking' => [
            ['label' => 'Services', 'route' => null],
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
        <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Workspace</h2>

        <x-dashboard.nav-item
            label="Overview"
            :href="route('dashboard')"
            :active="request()->routeIs('dashboard')"
        />
    </section>

    @foreach ($sections as $section => $items)
        <section>
            <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">{{ $section }}</h2>

            <div class="space-y-1">
                @foreach ($items as $item)
                    <x-dashboard.nav-item
                        :label="$item['label']"
                        :href="$item['route'] ? route($item['route']) : null"
                        :disabled="$item['route'] === null"
                    />
                @endforeach
            </div>
        </section>
    @endforeach

    <section>
        <h2 class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Administration</h2>

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
