@extends('layouts.dashboard')

@section('title', 'Booking Services')
@section('heading', 'Booking Services')

@section('content')
    <x-dashboard.page-header
        title="Booking Services"
        description="Configure the services your company offers through the Booking workspace."
    />

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-dashboard.card title="Service List" description="Services are tenant-scoped and paginated for growing catalogs.">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-surface">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Capacity</th>
                        <th class="px-4 py-3">Booking</th>
                        <th class="px-4 py-3">Status</th>
                        @if (auth()->user()->can('booking.services.manage'))
                            <th class="px-4 py-3 text-end">Actions</th>
                        @endif
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                    @forelse ($services as $service)
                        @php
                            $status = $service->status instanceof \BackedEnum
                                ? $service->status->value
                                : (string) $service->status;
                        @endphp

                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $service->name }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $service->slug }}</div>
                                @if ($service->description)
                                    <p class="mt-2 max-w-xs text-xs leading-5 text-muted">{{ $service->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-muted">
                                {{ $service->duration_minutes }} min
                                @if ($service->buffer_before_minutes || $service->buffer_after_minutes)
                                    <div class="mt-1 text-xs">Buffer: {{ $service->buffer_before_minutes }} / {{ $service->buffer_after_minutes }} min</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="font-medium text-secondary">{{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}</div>
                                @if ($service->deposit_amount_minor > 0)
                                    <div class="mt-1 text-xs text-muted">Deposit: {{ number_format($service->deposit_amount_minor / 100, 2) }} {{ $service->currency }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-muted">{{ $service->capacity }}</td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$service->online_bookable ? 'success' : 'neutral'">
                                    {{ $service->online_bookable ? 'Online bookable' : 'Offline only' }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$status === 'active' ? 'success' : 'neutral'">
                                    {{ ucfirst($status) }}
                                </x-dashboard.badge>
                            </td>

                            @if (auth()->user()->can('booking.services.manage'))
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <details>
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Edit</summary>
                                            <form method="POST" action="{{ route('dashboard.booking.services.update', $service) }}" class="mt-3 w-[min(32rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                @csrf
                                                @method('PATCH')

                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="sm:col-span-2">
                                                        <label class="mb-1 block text-xs font-medium text-muted">Name</label>
                                                        <input name="name" value="{{ $service->name }}" required maxlength="150" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Slug</label>
                                                        <input name="slug" value="{{ $service->slug }}" maxlength="180" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Currency</label>
                                                        <input name="currency" value="{{ $service->currency }}" required maxlength="3" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Duration (min)</label>
                                                        <input name="duration_minutes" type="number" value="{{ $service->duration_minutes }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Capacity</label>
                                                        <input name="capacity" type="number" value="{{ $service->capacity }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Price (minor units)</label>
                                                        <input name="price_minor" type="number" value="{{ $service->price_minor }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Deposit (minor units)</label>
                                                        <input name="deposit_amount_minor" type="number" value="{{ $service->deposit_amount_minor }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Before buffer</label>
                                                        <input name="buffer_before_minutes" type="number" value="{{ $service->buffer_before_minutes }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">After buffer</label>
                                                        <input name="buffer_after_minutes" type="number" value="{{ $service->buffer_after_minutes }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Status</label>
                                                        <select name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                            <option value="active" @selected($status === 'active')>Active</option>
                                                            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">Online booking</label>
                                                        <select name="online_bookable" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                            <option value="1" @selected($service->online_bookable)>Enabled</option>
                                                            <option value="0" @selected(!$service->online_bookable)>Disabled</option>
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="mb-1 block text-xs font-medium text-muted">Description</label>
                                                        <textarea name="description" rows="3" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ $service->description }}</textarea>
                                                    </div>
                                                </div>

                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">Save Changes</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('dashboard.booking.services.destroy', $service) }}" onsubmit="return confirm('Archive this service?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-dashboard.button variant="danger" size="sm" type="submit">Archive</x-dashboard.button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-muted">No booking services have been created yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <x-dashboard.pagination :paginator="$services" />
        </x-dashboard.card>

        @if (auth()->user()->can('booking.services.manage'))
            <x-dashboard.card title="Add Service" description="Create a bookable service using the existing Booking rules.">
                <form method="POST" action="{{ route('dashboard.booking.services.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted">Name</label>
                        <input name="name" value="{{ old('name') }}" required maxlength="150" placeholder="e.g. Consultation" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted">Description</label>
                        <textarea name="description" rows="3" placeholder="Describe the service" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Duration (min)</label>
                            <input name="duration_minutes" type="number" value="{{ old('duration_minutes', 60) }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Capacity</label>
                            <input name="capacity" type="number" value="{{ old('capacity', 1) }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Price (minor units)</label>
                            <input name="price_minor" type="number" value="{{ old('price_minor', 0) }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Currency</label>
                            <input name="currency" type="text" value="{{ old('currency', $tenant->default_currency) }}" required maxlength="3" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Deposit (minor units)</label>
                            <input name="deposit_amount_minor" type="number" value="{{ old('deposit_amount_minor', 0) }}" min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Before buffer</label>
                            <input name="buffer_before_minutes" type="number" value="{{ old('buffer_before_minutes', 0) }}" min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">After buffer</label>
                            <input name="buffer_after_minutes" type="number" value="{{ old('buffer_after_minutes', 0) }}" min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Status</label>
                            <select name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">Online booking</label>
                            <select name="online_bookable" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                <option value="1" @selected(old('online_bookable', '1') === '1')>Enabled</option>
                                <option value="0" @selected(old('online_bookable') === '0')>Disabled</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-xs leading-5 text-muted">Prices use minor currency units, matching the existing Booking backend contract.</p>
                    <x-dashboard.button type="submit" class="w-full sm:w-auto">Create Service</x-dashboard.button>
                </form>
            </x-dashboard.card>
        @endif
    </div>
@endsection
