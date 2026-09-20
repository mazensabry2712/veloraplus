<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(strtolower(app()->getLocale()), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · VeloraPlus</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-text antialiased">
<div class="min-h-screen lg:flex">
    <aside class="hidden w-72 shrink-0 border-r border-border bg-white lg:flex lg:min-h-screen lg:flex-col">
        <div class="border-b border-border px-6 py-5">
            <x-dashboard.brand />
            <p class="mt-4 truncate text-base font-semibold text-secondary">{{ $tenant->name }}</p>
            <p class="mt-1 text-xs text-muted">{{ $membership->role_key }}</p>
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto px-4 py-5" aria-label="Company navigation">
            <x-dashboard.navigation />
        </nav>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 border-b border-border bg-white/95 backdrop-blur">
            <div class="px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-h-10 items-center gap-3">
                    <details class="relative lg:hidden">
                        <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-lg border border-border bg-white px-3 text-sm font-medium text-secondary [&::-webkit-details-marker]:hidden">
                            <span aria-hidden="true">☰</span>
                            Menu
                        </summary>

                        <div class="absolute start-0 top-12 z-30 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-border bg-white p-3 shadow-lg">
                            <div class="mb-3 border-b border-border px-3 pb-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted">Company</p>
                                <p class="mt-1 truncate text-sm font-semibold text-secondary">{{ $tenant->name }}</p>
                            </div>

                            <nav aria-label="Mobile company navigation">
                                <x-dashboard.navigation />
                            </nav>
                        </div>
                    </details>

                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Company Dashboard</p>
                        <h1 class="truncate text-lg font-semibold text-secondary">@yield('heading', 'Overview')</h1>
                    </div>

                    <div class="hidden items-center gap-3 sm:flex">
                        <div class="max-w-48 text-end">
                            <p class="truncate text-sm font-medium text-secondary">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-muted">{{ $membership->role_key }}</p>
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dashboard.button variant="secondary" type="submit">
                                Sign out
                            </x-dashboard.button>
                        </form>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3 sm:hidden">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-secondary">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-muted">{{ $membership->role_key }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dashboard.button variant="secondary" size="sm" type="submit">
                            Sign out
                        </x-dashboard.button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            @if (session('status'))
                <div role="status" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please review the highlighted fields.</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
