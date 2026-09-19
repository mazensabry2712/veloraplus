@extends('layouts.dashboard')

@section('title', 'Overview')
@section('heading', 'Overview')

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-medium text-slate-500">Company</p>
            <p class="mt-2 text-lg font-semibold">{{ $tenant->name }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $tenant->slug }}.velora.test</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-medium text-slate-500">Your role</p>
            <p class="mt-2 text-lg font-semibold">{{ ucfirst($membership->role_key) }}</p>
            <p class="mt-1 text-sm text-slate-500">Tenant-scoped access</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-medium text-slate-500">Booking</p>
            <p class="mt-2 text-lg font-semibold">Available</p>
            <p class="mt-1 text-sm text-slate-500">Workspace screens are being delivered in Phase 8.</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-sm font-medium text-slate-500">Platform</p>
            <p class="mt-2 text-lg font-semibold">Connected</p>
            <p class="mt-1 text-sm text-slate-500">Core, billing, payments and entitlements are already in place.</p>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-6">
        <p class="text-sm font-semibold text-slate-900">Phase 8.1 Dashboard Shell</p>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
            This is the first authenticated Company Dashboard surface. Business screens will be enabled slice-by-slice
            while continuing to use the existing tenant membership, entitlement, permission, policy, and application-service boundaries.
        </p>
    </section>
@endsection
