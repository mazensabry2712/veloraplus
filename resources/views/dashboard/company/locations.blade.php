@extends('layouts.dashboard')

@section('title', __('dashboard.locations'))
@section('heading', __('dashboard.locations'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.locations')"
        :description="__('dashboard.page_descriptions.locations')"
    />

    <div class="mt-6 space-y-5">
        <x-dashboard.card :title="__('dashboard.company_pages.location_list')" :description="__('dashboard.company_pages.location_list_description')">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-primary/5">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.location') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.city') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.timezone') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.status') }}</th>
                        @if (auth()->user()->can('locations.manage'))
                            <th class="px-4 py-3 text-end">{{ __('dashboard.company_pages.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($locations as $location)
                        <tr class="align-top transition-colors hover:bg-primary/5">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $location->name }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $location->code ?: __('dashboard.company_pages.no_code') }}</div>
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
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">{{ __('dashboard.company_pages.edit') }}</summary>
                                            <form method="POST" action="{{ route('company.locations.update', $location) }}" class="mt-3 w-[min(28rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm" dir="{{ str_starts_with(strtolower(app()->getLocale()), 'ar') ? 'rtl' : 'ltr' }}">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <input name="name" value="{{ $location->name }}" required maxlength="120" placeholder="Name" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="code" value="{{ $location->code }}" maxlength="50" placeholder="Code" class="rounded-lg border border-border bg-white px-3 py-2 text-sm uppercase">
                                                    <input name="city" value="{{ $location->city }}" maxlength="100" placeholder="City" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="country_code" value="{{ $location->country_code }}" maxlength="2" :placeholder="__('dashboard.country_code')" class="rounded-lg border border-border bg-white px-3 py-2 text-sm uppercase">
                                                    <input name="timezone" value="{{ $location->timezone }}" required :placeholder="__('dashboard.company_pages.timezone')" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <select name="status" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <option value="active" @selected($location->status === 'active')>Active</option>
                                                        <option value="inactive" @selected($location->status === 'inactive')>Inactive</option>
                                                    </select>
                                                    <textarea name="address" rows="3" placeholder="Address" class="sm:col-span-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">{{ $location->address }}</textarea>
                                                </div>
                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">{{ __('dashboard.company_pages.save') }}</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('company.locations.destroy', $location) }}" onsubmit="return confirm(@js(__('dashboard.company_pages.archive_location_confirm')));">
                                            @csrf
                                            @method('DELETE')
                                            <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.company_pages.archive') }}</x-dashboard.button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-muted">{{ __('dashboard.company_pages.no_locations') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$locations" />
        </x-dashboard.card>

        @if (auth()->user()->can('locations.manage'))
            <details class="group" @if ($errors->any()) open @endif>
                <summary class="inline-flex min-h-10 w-full cursor-pointer list-none items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm font-semibold text-secondary transition-colors hover:border-primary/30 hover:bg-primary/5 [&::-webkit-details-marker]:hidden sm:w-auto">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'location'])</span>
                        {{ __('dashboard.company_pages.add_location') }}
                    </span>
                    <span class="text-muted transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
                </summary>
                <div class="mt-4">
                    <x-dashboard.card :title="__('dashboard.company_pages.add_location')" :description="__('dashboard.company_pages.add_location_description')">

                <form method="POST" action="{{ route('company.locations.store') }}" class="space-y-4">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="120" :placeholder="__('dashboard.company_pages.location_name')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="code" value="{{ old('code') }}" maxlength="50" :placeholder="__('dashboard.company_pages.location_code_placeholder')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                    <input name="city" value="{{ old('city') }}" maxlength="100" placeholder="City" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="country_code" value="{{ old('country_code', $tenant->country_code) }}" maxlength="2" :placeholder="__('dashboard.country_code')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                    <input name="timezone" value="{{ old('timezone', $tenant->timezone) }}" required :placeholder="__('dashboard.company_pages.timezone')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <textarea name="address" rows="4" placeholder="Address" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ old('address') }}</textarea>
                    <x-dashboard.button type="submit" class="w-full sm:w-auto">{{ __('dashboard.company_pages.create_location') }}</x-dashboard.button>
                </form>
                    </x-dashboard.card>
                </div>
            </details>
        @endif
    </div>
@endsection
