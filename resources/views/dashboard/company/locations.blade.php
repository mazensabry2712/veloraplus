@extends('layouts.dashboard')

@section('title', 'Locations')
@section('heading', 'Locations')

@section('content')
    <x-dashboard.page-header
        title="Locations"
        description="Manage the physical locations used by your company and its booking staff."
    />

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-dashboard.card title="Location List" description="Only active tenant locations are used for new staff assignments.">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-surface">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">City</th>
                        <th class="px-4 py-3">Timezone</th>
                        <th class="px-4 py-3">Status</th>
                        @if (auth()->user()->can('locations.manage'))
                            <th class="px-4 py-3 text-end">Actions</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($locations as $location)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $location->name }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $location->code ?: 'No code' }}</div>
                            </td>
                            <td class="px-4 py-4 text-muted">{{ $location->city ?: '—' }}</td>
                            <td class="px-4 py-4 text-muted whitespace-nowrap">{{ $location->timezone }}</td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$location->status === 'active' ? 'success' : 'neutral'">
                                    {{ ucfirst($location->status) }}
                                </x-dashboard.badge>
                            </td>
                            @if (auth()->user()->can('locations.manage'))
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <details>
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Edit</summary>
                                            <form method="POST" action="{{ route('company.locations.update', $location) }}" class="mt-3 w-[min(28rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm" dir="{{ str_starts_with(strtolower(app()->getLocale()), 'ar') ? 'rtl' : 'ltr' }}">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <input name="name" value="{{ $location->name }}" required maxlength="120" placeholder="Name" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="code" value="{{ $location->code }}" maxlength="50" placeholder="Code" class="rounded-lg border border-border bg-white px-3 py-2 text-sm uppercase">
                                                    <input name="city" value="{{ $location->city }}" maxlength="100" placeholder="City" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="country_code" value="{{ $location->country_code }}" maxlength="2" placeholder="EG" class="rounded-lg border border-border bg-white px-3 py-2 text-sm uppercase">
                                                    <input name="timezone" value="{{ $location->timezone }}" required placeholder="Africa/Cairo" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <select name="status" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <option value="active" @selected($location->status === 'active')>Active</option>
                                                        <option value="inactive" @selected($location->status === 'inactive')>Inactive</option>
                                                    </select>
                                                    <textarea name="address" rows="3" placeholder="Address" class="sm:col-span-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">{{ $location->address }}</textarea>
                                                </div>
                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">Save</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('company.locations.destroy', $location) }}" onsubmit="return confirm('Archive this location?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-dashboard.button variant="danger" size="sm" type="submit">Archive</x-dashboard.button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-muted">No locations have been created yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$locations" />
        </x-dashboard.card>

        @if (auth()->user()->can('locations.manage'))
            <x-dashboard.card title="Add Location" description="Create a location before assigning staff to it.">
                <form method="POST" action="{{ route('company.locations.store') }}" class="space-y-4">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Location name" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="code" value="{{ old('code') }}" maxlength="50" placeholder="Code (e.g. MAIN-01)" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                    <input name="city" value="{{ old('city') }}" maxlength="100" placeholder="City" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="country_code" value="{{ old('country_code', $tenant->country_code) }}" maxlength="2" placeholder="EG" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                    <input name="timezone" value="{{ old('timezone', $tenant->timezone) }}" required placeholder="Africa/Cairo" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <textarea name="address" rows="4" placeholder="Address" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ old('address') }}</textarea>
                    <x-dashboard.button type="submit" class="w-full sm:w-auto">Create Location</x-dashboard.button>
                </form>
            </x-dashboard.card>
        @endif
    </div>
@endsection
