@extends('layouts.dashboard')

@section('title', __('dashboard.staff'))
@section('heading', __('dashboard.staff'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.staff')"
        :description="__('dashboard.page_descriptions.staff')"
    />

    <div class="mt-6 space-y-5">
        <x-dashboard.card title="Staff List" description="The list is paginated to keep the dashboard lightweight as your team grows.">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-primary/5">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">Staff Member</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Status</th>
                        @if (auth()->user()->can('staff.manage'))
                            <th class="px-4 py-3 text-end">Actions</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($staff as $member)
                        <tr class="align-top transition-colors hover:bg-primary/5">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $member->name }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $member->email ?: 'No email' }}</div>
                            </td>
                            <td class="px-4 py-4 text-muted">{{ $member->location?->name ?: 'Unassigned' }}</td>
                            <td class="px-4 py-4 text-muted whitespace-nowrap">{{ $member->phone ?: '—' }}</td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$member->status === 'active' ? 'success' : 'neutral'">
                                    {{ ucfirst($member->status) }}
                                </x-dashboard.badge>
                            </td>
                            @if (auth()->user()->can('staff.manage'))
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <details>
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Edit</summary>
                                            <form method="POST" action="{{ route('company.staff.update', $member) }}" class="mt-3 w-[min(30rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <input name="name" value="{{ $member->name }}" required maxlength="150" placeholder="Name" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="phone" value="{{ $member->phone }}" maxlength="50" placeholder="Phone" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="email" type="email" value="{{ $member->email }}" maxlength="190" placeholder="Email" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <select name="location_id" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <option value="">No location</option>
                                                        @foreach ($locations as $location)
                                                            <option value="{{ $location->id }}" @selected($member->location_id === $location->id)>{{ $location->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select name="status" class="sm:col-span-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <option value="active" @selected($member->status === 'active')>Active</option>
                                                        <option value="inactive" @selected($member->status === 'inactive')>Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">Save</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('company.staff.destroy', $member) }}" onsubmit="return confirm('Archive this staff member?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-dashboard.button variant="danger" size="sm" type="submit">Archive</x-dashboard.button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-muted">No staff members have been created yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$staff" />
        </x-dashboard.card>

        @if (auth()->user()->can('staff.manage'))
            <details class="group" @if ($errors->any()) open @endif>
                <summary class="inline-flex min-h-10 w-full cursor-pointer list-none items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm font-semibold text-secondary transition-colors hover:border-primary/30 hover:bg-primary/5 [&::-webkit-details-marker]:hidden sm:w-auto">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'users'])</span>
                        Add Staff
                    </span>
                    <span class="text-muted transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
                </summary>
                <div class="mt-4">
                    <x-dashboard.card title="Add Staff" description="A staff member can optionally be associated with an active location.">

                <form method="POST" action="{{ route('company.staff.store') }}" class="space-y-4">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="150" placeholder="Full name" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="phone" value="{{ old('phone') }}" maxlength="50" placeholder="Phone" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="email" type="email" value="{{ old('email') }}" maxlength="190" placeholder="Email" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <select name="location_id" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="">No location</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id') === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    <x-dashboard.button type="submit">Create Staff Member</x-dashboard.button>
                </form>
                    </x-dashboard.card>
                </div>
            </details>
        @endif
    </div>
@endsection
