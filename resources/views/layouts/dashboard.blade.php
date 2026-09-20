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
<body class="dashboard-shell min-h-full bg-surface font-sans text-text antialiased">
<div class="min-h-screen lg:flex">
    <aside class="hidden w-72 shrink-0 border-e border-border bg-white lg:flex lg:min-h-screen lg:flex-col">
        <div class="border-b border-border bg-surface/70 px-5 py-5">
            <x-dashboard.brand />

            <div class="mt-5 rounded-2xl border border-primary/10 bg-primary/5 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">{{ __('dashboard.company') }}</p>
                        <p class="mt-1 truncate text-sm font-semibold text-secondary">{{ $tenant->name }}</p>
                    </div>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-primary shadow-sm" aria-hidden="true">
                        @include('components.dashboard.icon', ['name' => 'building'])
                    </span>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-primary/10 pt-3">
                    <span class="text-xs font-medium text-muted">{{ ucfirst($membership->role_key) }}</span>
                    <span class="rounded-full bg-primary px-2.5 py-1 text-[10px] font-semibold text-white">{{ strtoupper((string) ($tenant->default_currency ?: 'EGP')) }}</span>
                </div>
            </div>
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto px-4 py-5" aria-label="{{ __('dashboard.company') }}">
            <x-dashboard.navigation />
        </nav>

        <div class="border-t border-border p-4">
            <button
                type="button"
                data-command-trigger
                class="group flex w-full items-center justify-between gap-3 rounded-xl border border-border bg-white px-3.5 py-2.5 text-start transition-all hover:border-primary/20 hover:bg-primary/5"
            >
                <span class="flex min-w-0 items-center gap-2.5">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-surface text-muted transition-colors group-hover:bg-primary/10 group-hover:text-primary" aria-hidden="true">
                        @include('components.dashboard.icon', ['name' => 'search'])
                    </span>
                    <span class="truncate text-sm font-medium text-secondary">{{ __('dashboard.search') }}</span>
                </span>
                <kbd class="hidden rounded-md border border-border bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-muted xl:inline-flex">{{ __('dashboard.command_hint') }}</kbd>
            </button>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 border-b border-border bg-white/95 backdrop-blur">
            <div class="px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-h-10 items-center gap-3">
                    <details class="relative lg:hidden">
                        <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-border bg-white px-3 text-sm font-medium text-secondary shadow-sm [&::-webkit-details-marker]:hidden">
                            <span aria-hidden="true">☰</span>
                            {{ __('dashboard.menu') }}
                        </summary>

                        <div class="absolute start-0 top-12 z-30 w-[min(21rem,calc(100vw-2rem))] rounded-2xl border border-border bg-white p-3 shadow-xl">
                            <div class="mb-3 border-b border-border px-3 pb-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted">{{ __('dashboard.company') }}</p>
                                <p class="mt-1 truncate text-sm font-semibold text-secondary">{{ $tenant->name }}</p>
                            </div>

                            <nav aria-label="{{ __('dashboard.company') }}">
                                <x-dashboard.navigation />
                            </nav>
                        </div>
                    </details>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted sm:text-[11px]">{{ __('dashboard.company_dashboard') }}</p>
                            <span class="hidden h-1 w-1 rounded-full bg-accent sm:inline-block" aria-hidden="true"></span>
                            <span class="hidden text-[11px] font-medium text-muted sm:inline-block">{{ $tenant->name }}</span>
                        </div>
                        <h1 class="truncate text-lg font-semibold tracking-tight text-secondary">@yield('heading', __('dashboard.overview'))</h1>
                    </div>

                    <div class="hidden items-center gap-1 sm:flex">
                        <button
                            type="button"
                            data-command-trigger
                            class="hidden h-10 items-center gap-2 rounded-xl border border-border bg-white px-3 text-sm text-muted transition-colors hover:border-primary/20 hover:bg-primary/5 hover:text-primary lg:flex"
                            aria-label="{{ __('dashboard.search') }}"
                        >
                            @include('components.dashboard.icon', ['name' => 'search'])
                            <span>{{ __('dashboard.search') }}</span>
                            <kbd class="rounded-md border border-border bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-muted">{{ __('dashboard.command_hint') }}</kbd>
                        </button>

                        <button
                            type="button"
                            data-theme-toggle
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-white text-secondary transition-all hover:border-primary/20 hover:bg-primary/5 hover:text-primary"
                            aria-label="{{ __('dashboard.theme') }}"
                            title="{{ __('dashboard.theme') }}"
                        >
                            <span data-theme-icon="moon" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'moon'])</span>
                            <span data-theme-icon="sun" aria-hidden="true" hidden>@include('components.dashboard.icon', ['name' => 'sun'])</span>
                        </button>

                        <details class="relative">
                            <summary class="flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-border bg-white px-3 text-sm font-medium text-secondary transition-colors hover:border-primary/20 hover:bg-primary/5 [&::-webkit-details-marker]:hidden">
                                @include('components.dashboard.icon', ['name' => 'globe'])
                                <span>{{ app()->getLocale() === 'ar' ? 'عربي' : 'EN' }}</span>
                            </summary>

                            <div class="absolute end-0 top-12 z-40 w-44 rounded-2xl border border-border bg-white p-2 shadow-xl">
                                <p class="px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">{{ __('dashboard.language') }}</p>

                                @foreach (['en' => __('dashboard.english'), 'ar' => __('dashboard.arabic')] as $locale => $label)
                                    <form method="POST" action="{{ route('dashboard.preferences.locale') }}">
                                        @csrf
                                        <input type="hidden" name="locale" value="{{ $locale }}">
                                        <button type="submit" class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm transition-colors hover:bg-primary/5 hover:text-primary">
                                            <span>{{ $label }}</span>
                                            @if (app()->getLocale() === $locale)
                                                <span class="text-primary" aria-hidden="true">✓</span>
                                            @endif
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </details>

                        <div class="mx-2 hidden h-7 w-px bg-border lg:block" aria-hidden="true"></div>

                        <div class="max-w-48 text-end">
                            <p class="truncate text-sm font-medium text-secondary">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-muted">{{ ucfirst($membership->role_key) }}</p>
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dashboard.button variant="secondary" type="submit">
                                {{ __('dashboard.sign_out') }}
                            </x-dashboard.button>
                        </form>
                    </div>

                    <div class="flex items-center gap-1 sm:hidden">
                        <button type="button" data-theme-toggle class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-white text-secondary" aria-label="{{ __('dashboard.theme') }}">
                            <span data-theme-icon="moon" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'moon'])</span>
                            <span data-theme-icon="sun" aria-hidden="true" hidden>@include('components.dashboard.icon', ['name' => 'sun'])</span>
                        </button>
                        <details class="relative">
                            <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl border border-border bg-white text-secondary [&::-webkit-details-marker]:hidden" aria-label="{{ __('dashboard.language') }}">
                                @include('components.dashboard.icon', ['name' => 'globe'])
                            </summary>
                            <div class="absolute end-0 top-12 z-40 w-40 rounded-2xl border border-border bg-white p-2 shadow-xl">
                                @foreach (['en' => __('dashboard.english'), 'ar' => __('dashboard.arabic')] as $locale => $label)
                                    <form method="POST" action="{{ route('dashboard.preferences.locale') }}">
                                        @csrf
                                        <input type="hidden" name="locale" value="{{ $locale }}">
                                        <button type="submit" class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm hover:bg-primary/5 hover:text-primary">
                                            <span>{{ $label }}</span>
                                            @if (app()->getLocale() === $locale)
                                                <span class="text-primary" aria-hidden="true">✓</span>
                                            @endif
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3 sm:hidden">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-secondary">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-muted">{{ ucfirst($membership->role_key) }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dashboard.button variant="secondary" size="sm" type="submit">
                            {{ __('dashboard.sign_out') }}
                        </x-dashboard.button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
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
            <span class="text-muted" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'search'])</span>
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
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors hover:bg-primary/5 hover:text-primary"
                    >
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface text-muted">
                            @include('components.dashboard.icon', ['name' => $command['icon']])
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-secondary">{{ $command['label'] }}</span>
                        <span class="text-muted" aria-hidden="true">→</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
</body>
</html>
