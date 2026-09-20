@extends('layouts.dashboard')

@section('title', __('dashboard.services'))
@section('heading', __('dashboard.services'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.services')"
        :description="__('dashboard.page_descriptions.services')"
    />

    <div class="mt-6 space-y-5">
        <x-dashboard.card :title="__('dashboard.booking_pages.service_list')" :description="__('dashboard.booking_pages.service_list_description')">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-primary/5">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">{{ __('dashboard.booking_pages.service') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.booking_pages.duration') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.booking_pages.price') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.booking_pages.capacity') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.booking_pages.booking') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.booking_pages.status') }}</th>
                        @if (auth()->user()->can('booking.services.manage'))
                            <th class="px-4 py-3 text-end">{{ __('dashboard.booking_pages.actions') }}</th>
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

                        <tr class="align-top transition-colors hover:bg-primary/5">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $service->name }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $service->slug }}</div>
                                @if ($service->description)
                                    <p class="mt-2 max-w-xs text-xs leading-5 text-muted">{{ $service->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-muted">
                                {{ $service->duration_minutes }} {{ __('dashboard.minutes') }}
                                @if ($service->buffer_before_minutes || $service->buffer_after_minutes)
                                    <div class="mt-1 text-xs">{{ __('dashboard.booking_pages.before_buffer') }}: {{ $service->buffer_before_minutes }} / {{ $service->buffer_after_minutes }} {{ __('dashboard.minutes') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="font-medium text-secondary">{{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}</div>
                                @if ($service->deposit_amount_minor > 0)
                                    <div class="mt-1 text-xs text-muted">{{ __('dashboard.booking_pages.deposit_minor') }}: {{ number_format($service->deposit_amount_minor / 100, 2) }} {{ $service->currency }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-muted">{{ $service->capacity }}</td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$service->online_bookable ? 'success' : 'neutral'">
                                    {{ $service->online_bookable ? {{ __('dashboard.booking_pages.online_bookable') }} : {{ __('dashboard.booking_pages.offline_only') }} }}
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
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">{{ __('dashboard.booking_pages.edit') }}</summary>
                                            <form method="POST" action="{{ route('dashboard.booking.services.update', $service) }}" class="mt-3 w-[min(32rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                @csrf
                                                @method('PATCH')

                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="sm:col-span-2">
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.name') }}</label>
                                                        <input name="name" value="{{ $service->name }}" required maxlength="150" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.slug') }}</label>
                                                        <input name="slug" value="{{ $service->slug }}" maxlength="180" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.currency') }}</label>
                                                        <input name="currency" value="{{ $service->currency }}" required maxlength="3" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.duration_minutes') }}</label>
                                                        <input name="duration_minutes" type="number" value="{{ $service->duration_minutes }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.capacity') }}</label>
                                                        <input name="capacity" type="number" value="{{ $service->capacity }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.price_minor') }}</label>
                                                        <input name="price_minor" type="number" value="{{ $service->price_minor }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.deposit_minor') }}</label>
                                                        <input name="deposit_amount_minor" type="number" value="{{ $service->deposit_amount_minor }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.before_buffer') }}</label>
                                                        <input name="buffer_before_minutes" type="number" value="{{ $service->buffer_before_minutes }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.after_buffer') }}</label>
                                                        <input name="buffer_after_minutes" type="number" value="{{ $service->buffer_after_minutes }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.status') }}</label>
                                                        <select name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                            <option value="active" @selected($status === 'active')>{{ __('dashboard.booking_pages.enabled') }}</option>
                                                            <option value="inactive" @selected($status === 'inactive')>{{ __('dashboard.booking_pages.disabled') }}</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.online_booking') }}</label>
                                                        <select name="online_bookable" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                            <option value="1" @selected($service->online_bookable)>{{ __('dashboard.booking_pages.enabled') }}</option>
                                                            <option value="0" @selected(!$service->online_bookable)>{{ __('dashboard.booking_pages.disabled') }}</option>
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.description') }}</label>
                                                        <textarea name="description" rows="3" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ $service->description }}</textarea>
                                                    </div>
                                                </div>

                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">{{ __('dashboard.booking_pages.save_changes') }}</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('dashboard.booking.services.destroy', $service) }}" onsubmit="return confirm(@js(__('dashboard.booking_pages.archive_service_confirm')));">
                                            @csrf
                                            @method('DELETE')
                                            <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.booking_pages.archive') }}</x-dashboard.button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-muted">{{ __('dashboard.booking_pages.no_services') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$services" />
        </x-dashboard.card>

        @if (auth()->user()->can('booking.services.manage'))
            <details class="group" @if ($errors->any()) open @endif>
                <summary class="inline-flex min-h-10 w-full cursor-pointer list-none items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm font-semibold text-secondary transition-colors hover:border-primary/30 hover:bg-primary/5 [&::-webkit-details-marker]:hidden sm:w-auto">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'briefcase'])</span>
{{ __('dashboard.booking_pages.add_service') }}
                    </span>
                    <span class="text-muted transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
                </summary>
                <div class="mt-4">
                    <x-dashboard.card :title="__('dashboard.booking_pages.add_service')" :description="__('dashboard.booking_pages.add_service_description')">

                <form method="POST" action="{{ route('dashboard.booking.services.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.name') }}</label>
                        <input name="name" value="{{ old('name') }}" required maxlength="150" :placeholder="__('dashboard.booking_pages.consultation_placeholder')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.description') }}</label>
                        <textarea name="description" rows="3" :placeholder="__('dashboard.booking_pages.describe_service')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.duration_minutes') }}</label>
                            <input name="duration_minutes" type="number" value="{{ old('duration_minutes', 60) }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.capacity') }}</label>
                            <input name="capacity" type="number" value="{{ old('capacity', 1) }}" required min="1" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.price_minor') }}</label>
                            <input name="price_minor" type="number" value="{{ old('price_minor', 0) }}" required min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.currency') }}</label>
                            <input name="currency" type="text" value="{{ old('currency', $tenant->default_currency) }}" required maxlength="3" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.deposit_minor') }}</label>
                            <input name="deposit_amount_minor" type="number" value="{{ old('deposit_amount_minor', 0) }}" min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.before_buffer') }}</label>
                            <input name="buffer_before_minutes" type="number" value="{{ old('buffer_before_minutes', 0) }}" min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.after_buffer') }}</label>
                            <input name="buffer_after_minutes" type="number" value="{{ old('buffer_after_minutes', 0) }}" min="0" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.status') }}</label>
                            <select name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                <option value="active" @selected(old('status', 'active') === 'active')>{{ __('dashboard.booking_pages.enabled') }}</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>{{ __('dashboard.booking_pages.disabled') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('dashboard.booking_pages.online_booking') }}</label>
                            <select name="online_bookable" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                <option value="1" @selected(old('online_bookable', '1') === '1')>{{ __('dashboard.booking_pages.enabled') }}</option>
                                <option value="0" @selected(old('online_bookable') === '0')>{{ __('dashboard.booking_pages.disabled') }}</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-xs leading-5 text-muted">{{ __('dashboard.booking_pages.minor_units_hint') }}</p>
                    <x-dashboard.button type="submit" class="w-full sm:w-auto">{{ __('dashboard.booking_pages.create_service') }}</x-dashboard.button>
                </form>
                    </x-dashboard.card>
                </div>
            </details>
        @endif
    </div>
@endsection
