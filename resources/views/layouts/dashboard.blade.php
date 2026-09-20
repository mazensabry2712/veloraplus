<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(strtolower(app()->getLocale()), 'ar') ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0F172A">
    <title>@yield('title', __('dashboard.company_dashboard')) · VeloraPlus</title>
    <meta name="robots" content="noindex, nofollow, noarchive">

    <script>
        (() => {
            const key = 'veloraplus-theme';
            const saved = localStorage.getItem(key);
            const dark = saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches);

            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-shell min-h-full font-sans text-text antialiased">
<div class="min-h-screen lg:flex">
    <aside class="dashboard-sidebar hidden shrink-0 border-e lg:flex lg:min-h-screen lg:flex-col">
        <div class="dashboard-sidebar-header border-b px-5 py-5">
            <x-dashboard.brand />

            <div class="dashboard-tenant-card mt-5 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-sm" aria-hidden="true">
                        @include('components.dashboard.icon', ['name' => 'building'])
                    </span>

                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">{{ __('dashboard.company') }}</p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-secondary">{{ $tenant->name }}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 border-t border-primary/10 pt-3">
                    <span class="text-xs font-medium text-muted">{{ __('dashboard.roles.'.$membership->role_key) }}</span>
                    <span class="rounded-full bg-primary px-2.5 py-1 text-[10px] font-semibold text-white">
                        {{ strtoupper((string) ($tenant->default_currency ?: 'EGP')) }}
                    </span>
                </div>
            </div>
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto px-4 py-5" aria-label="{{ __('dashboard.company') }}">
            <x-dashboard.navigation />
        </nav>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="dashboard-topbar sticky top-0 z-30 border-b backdrop-blur-xl">
            <div class="mx-auto flex min-h-16 w-full max-w-[1600px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                <details class="relative lg:hidden">
                    <summary class="dashboard-topbar-button flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl text-sm [&::-webkit-details-marker]:hidden" aria-label="{{ __('dashboard.menu') }}">
                        <span aria-hidden="true">☰</span>
                    </summary>

                    <div class="absolute start-0 top-12 z-40 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-border bg-white p-3 shadow-2xl">
                        <div class="mb-3 border-b border-border px-3 pb-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">{{ __('dashboard.company') }}</p>
                            <p class="mt-1 truncate text-sm font-semibold text-secondary">{{ $tenant->name }}</p>
                        </div>

                        <nav aria-label="{{ __('dashboard.company') }}">
                            <x-dashboard.navigation />
                        </nav>
                    </div>
                </details>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="hidden text-[10px] font-semibold uppercase tracking-[0.14em] text-muted sm:inline">
                            {{ __('dashboard.company_dashboard') }}
                        </span>
                        <span class="hidden h-1 w-1 rounded-full bg-accent sm:inline-block" aria-hidden="true"></span>
                        <span class="hidden truncate text-[11px] font-medium text-muted md:inline">{{ $tenant->name }}</span>
                    </div>
                    <h1 class="truncate text-lg font-semibold tracking-tight text-secondary">
                        @yield('heading', __('dashboard.overview'))
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        data-command-trigger
                        class="dashboard-topbar-button flex h-10 min-w-10 items-center gap-2 rounded-xl px-3 text-sm sm:min-w-36"
                        aria-label="{{ __('dashboard.search') }}"
                    >
                        @include('components.dashboard.icon', ['name' => 'search'])
                        <span class="hidden sm:inline">{{ __('dashboard.search') }}</span>
                        <kbd class="hidden rounded-md border border-border bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-muted lg:inline-flex">{{ __('dashboard.command_hint') }}</kbd>
                    </button>

                    <details class="relative">
                        <summary class="dashboard-topbar-button flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl [&::-webkit-details-marker]:hidden sm:w-auto sm:gap-2 sm:px-3">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-xs font-semibold text-white">
                                {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="hidden max-w-28 truncate text-sm font-medium sm:inline">{{ auth()->user()->name }}</span>
                            <span class="hidden text-muted sm:inline" aria-hidden="true">⌄</span>
                        </summary>

                        <div class="absolute end-0 top-12 z-40 w-64 rounded-2xl border border-border bg-white p-2 shadow-2xl">
                            <div class="border-b border-border px-3 py-3">
                                <p class="truncate text-sm font-semibold text-secondary">{{ auth()->user()->name }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted">{{ __('dashboard.roles.'.$membership->role_key) }} · {{ $tenant->name }}</p>
                            </div>

                            <div class="px-1 py-2">
                                <button type="button" data-theme-toggle class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-secondary hover:bg-primary/5 hover:text-primary">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-surface text-muted">
                                        <span data-theme-icon="moon" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'moon'])</span>
                                        <span data-theme-icon="sun" aria-hidden="true" hidden>@include('components.dashboard.icon', ['name' => 'sun'])</span>
                                    </span>
                                    <span class="flex-1 text-start">
                                        <span class="block font-medium">{{ __('dashboard.theme') }}</span>
                                        <span class="block text-xs text-muted">
                                            <span data-theme-label data-dark-label="{{ __('dashboard.light') }}" data-light-label="{{ __('dashboard.dark') }}">{{ __('dashboard.dark') }}</span>
                                        </span>
                                    </span>
                                </button>

                                <div class="my-1 border-t border-border"></div>

                                <div class="rounded-xl px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">{{ __('dashboard.language') }}</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        @foreach (['en' => __('dashboard.english'), 'ar' => __('dashboard.arabic')] as $locale => $label)
                                            <form method="POST" action="{{ route('dashboard.preferences.locale') }}">
                                                @csrf
                                                <input type="hidden" name="locale" value="{{ $locale }}">
                                                <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold transition-colors hover:border-primary/20 hover:bg-primary/5 hover:text-primary">
                                                    {{ $label }}
                                                    @if (app()->getLocale() === $locale)
                                                        <span class="text-primary" aria-hidden="true">✓</span>
                                                    @endif
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-border p-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-secondary hover:bg-red-50 hover:text-red-700">
                                        @include('components.dashboard.icon', ['name' => 'arrow-right'])
                                        <span>{{ __('dashboard.sign_out') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] px-4 py-7 sm:px-6 sm:py-9 lg:px-8">
            @if (session('status'))
                <div role="status" class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please review the highlighted fields.</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<div
    data-command-palette
    hidden
    class="fixed inset-0 z-50 bg-slate-950/45 p-4 backdrop-blur-sm sm:p-8"
    role="dialog"
    aria-modal="true"
    aria-labelledby="command-palette-title"
>
    <div class="mx-auto mt-[8vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-border bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-border px-4 py-3">
            <span class="text-muted" aria-hidden="true">
                @include('components.dashboard.icon', ['name' => 'search'])
            </span>
            <input
                data-command-input
                type="search"
                autocomplete="off"
                placeholder="{{ __('dashboard.search_placeholder') }}"
                class="min-w-0 flex-1 border-0 bg-transparent px-0 text-base text-secondary outline-none placeholder:text-muted focus:ring-0"
            >
            <kbd class="hidden rounded-md border border-border bg-surface px-2 py-1 text-[10px] font-semibold text-muted sm:inline-flex">Esc</kbd>
            <button type="button" data-command-close class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-muted hover:bg-surface hover:text-secondary" aria-label="Close">×</button>
        </div>

        <div class="max-h-[55vh] overflow-y-auto p-2">
            <p id="command-palette-title" class="px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">{{ __('dashboard.available_workspaces') }}</p>

            @php
                $commandItems = [
                    ['label' => __('dashboard.overview'), 'route' => 'dashboard', 'icon' => 'grid'],
                    ['label' => __('dashboard.profile'), 'route' => 'company.profile', 'permission' => 'company.view', 'icon' => 'building'],
                    ['label' => __('dashboard.locations'), 'route' => 'company.locations', 'permission' => 'locations.view', 'icon' => 'location'],
                    ['label' => __('dashboard.staff'), 'route' => 'company.staff', 'permission' => 'staff.view', 'icon' => 'users'],
                    ['label' => __('dashboard.customers'), 'route' => 'company.customers', 'permission' => 'customers.view', 'icon' => 'user'],
                    ['label' => __('dashboard.users'), 'route' => 'company.users', 'permission' => 'members.view', 'icon' => 'users'],
                    ['label' => __('dashboard.roles_permissions'), 'route' => 'company.roles', 'permission' => 'members.view', 'icon' => 'shield'],
                    ['label' => __('dashboard.services'), 'route' => 'dashboard.booking.services.index', 'permission' => 'booking.services.view', 'entitlement' => 'booking.services', 'icon' => 'briefcase'],
                    ['label' => __('dashboard.availability'), 'route' => 'dashboard.booking.availability.index', 'permission' => 'booking.availability.view', 'entitlement' => 'booking.availability', 'icon' => 'clock'],
                    ['label' => __('dashboard.appointments'), 'route' => 'dashboard.booking.appointments.index', 'permission' => 'booking.appointments.view', 'entitlement' => 'booking.appointments', 'icon' => 'calendar'],
                    ['label' => __('dashboard.queue'), 'route' => 'dashboard.booking.queues.index', 'permission' => 'booking.queues.view', 'entitlement' => 'booking.queues', 'icon' => 'queue'],
                    ['label' => __('dashboard.usage_limits'), 'route' => 'dashboard.usage', 'permission' => 'settings.view', 'icon' => 'chart'],
                    ['label' => __('dashboard.settings'), 'route' => 'dashboard.settings', 'permission' => 'settings.view', 'icon' => 'settings'],
                ];
            @endphp

            @foreach ($commandItems as $command)
                @php
                    $commandAllowed = ($command['permission'] ?? null) === null
                        || (auth()->user()->can($command['permission'])
                            && (($command['entitlement'] ?? null) === null
                                || app('App\Application\Entitlements\EntitlementService')->canUse(
                                    app('App\Domain\Tenancy\TenantContext')->current(),
                                    $command['entitlement'],
                                )));
                @endphp

                @if ($commandAllowed)
                    <a
                        data-command-item
                        data-search="{{ strtolower($command['label']) }}"
                        data-active="false"
                        href="{{ route($command['route']) }}"
                        class="dashboard-command-item"
                    >
                        <span class="dashboard-command-icon">
                            @include('components.dashboard.icon', ['name' => $command['icon']])
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $command['label'] }}</span>
                        <span class="text-muted" aria-hidden="true">→</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
</body>
</html>
