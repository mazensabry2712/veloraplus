<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · VeloraPlus</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="min-h-screen md:flex">
    <aside class="w-full border-b border-slate-200 bg-white md:min-h-screen md:w-64 md:border-b-0 md:border-r">
        <div class="flex items-center justify-between px-5 py-5">
            <div>
                <p class="text-sm font-semibold tracking-wide text-slate-500">VeloraPlus</p>
                <h1 class="text-lg font-semibold">{{ $tenant->name }}</h1>
            </div>
        </div>

        <nav class="space-y-1 px-3 pb-4" aria-label="Company navigation">
            <a href="{{ route('dashboard') }}"
               class="block rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-900">
                Overview
            </a>

            <div class="px-3 pt-5 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                Company
            </div>
            @foreach (['Profile', 'Locations', 'Staff', 'Customers', 'Users', 'Roles & Permissions'] as $item)
                <span class="block rounded-lg px-3 py-2 text-sm text-slate-400">{{ $item }} <span class="text-xs">(next)</span></span>
            @endforeach

            <div class="px-3 pt-5 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                Booking
            </div>
            @foreach (['Services', 'Availability', 'Appointments', 'Queue', 'Payments'] as $item)
                <span class="block rounded-lg px-3 py-2 text-sm text-slate-400">{{ $item }} <span class="text-xs">(next)</span></span>
            @endforeach

            <div class="px-3 pt-5 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                Platform
            </div>
            @foreach (['Subscription', 'Module Marketplace'] as $item)
                <span class="block rounded-lg px-3 py-2 text-sm text-slate-400">{{ $item }} <span class="text-xs">(next)</span></span>
            @endforeach

            <a href="{{ route('dashboard.usage') }}"
               class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                Usage & Limits
            </a>

            <a href="{{ route('dashboard.settings') }}"
               class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                Settings
            </a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="border-b border-slate-200 bg-white">
            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Company Dashboard</p>
                    <h2 class="text-xl font-semibold">@yield('heading', 'Overview')</h2>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ $membership->role_key }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="px-5 py-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
