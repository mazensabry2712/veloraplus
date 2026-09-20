@extends('layouts.dashboard')

@section('title', __('dashboard.availability'))
@section('heading', __('dashboard.availability'))

@section('content')
    @php
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    @endphp

    <x-dashboard.page-header
        :title="__('dashboard.availability')"
        :description="__('dashboard.page_descriptions.availability')"
    />

    <div class="mt-6 space-y-6">
        @forelse ($staffMembers as $staff)
            <x-dashboard.card>
                <div class="flex flex-col gap-4 border-b border-border pb-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-secondary">{{ $staff->name }}</h2>
                            <x-dashboard.badge variant="success">{{ __('dashboard.booking_pages.availability_staff_active') }}</x-dashboard.badge>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span>{{ $staff->location?->name ?? {{ __('dashboard.booking_pages.no_location') }} }}</span>
                            @if ($staff->email)
                                <span>{{ $staff->email }}</span>
                            @endif
                            @if ($staff->phone)
                                <span>{{ $staff->phone }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($canManage && $servicesEntitled && $services->isNotEmpty())
                        @php
                            $assignedServiceIds = $staff->services
                                ->pluck('id')
                                ->map(static fn ($id): string => (string) $id)
                                ->all();
                        @endphp

                        @php
                            $unassignedServices = $services->reject(
                                static fn ($service): bool => in_array((string) $service->getKey(), $assignedServiceIds, true)
                            );
                        @endphp

                        @if ($unassignedServices->isNotEmpty())
                            <details>
                                <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">{{ __('dashboard.booking_pages.assign_service') }}</summary>
                                <div class="mt-3 flex flex-wrap gap-2 rounded-xl border border-border bg-surface p-3">
                                    @foreach ($unassignedServices as $service)
                                        <form method="POST" action="{{ route('dashboard.booking.availability.assign-service', [$staff, $service]) }}">
                                            @csrf
                                            <x-dashboard.button type="submit" size="sm">{{ $service->name }}</x-dashboard.button>
                                        </form>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endif
                </div>

                <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]">
                    <div class="space-y-5">
                        <section>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-medium text-secondary">{{ __('dashboard.booking_pages.assigned_services') }}</h3>
                                    <p class="mt-1 text-xs text-muted">{{ __('dashboard.booking_pages.assigned_services_description') }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($staff->services as $service)
                                    <div class="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3 py-1.5 text-xs text-secondary">
                                        <span>{{ $service->name }}</span>
                                        @if ($canManage && $servicesEntitled)
                                            <form method="POST" action="{{ route('dashboard.booking.availability.unassign-service', [$staff, $service]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-semibold text-muted hover:text-secondary" :title="__('dashboard.booking_pages.remove_service')" aria-label="Remove {{ $service->name }}">×</button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-sm text-muted">{{ __('dashboard.booking_pages.no_services_assigned') }}</span>
                                @endforelse
                            </div>
                        </section>

                        <section>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-medium text-secondary">{{ __('dashboard.booking_pages.working_hours') }}</h3>
                                    <p class="mt-1 text-xs text-muted">{{ __('dashboard.booking_pages.working_hours_description') }}</p>
                                </div>
                            </div>

                            <div class="mt-3 space-y-3">
                                @forelse ($staff->workingHours as $workingHour)
                                    <div class="rounded-xl border border-border bg-surface p-4">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div>
                                                <div class="font-medium text-secondary">
                                                    {{ $days[(int) $workingHour->day_of_week] ?? 'Day '.$workingHour->day_of_week }}
                                                </div>
                                                <div class="mt-1 text-sm text-muted">
                                                    {{ substr((string) $workingHour->starts_at, 0, 5) }}
                                                    –
                                                    {{ substr((string) $workingHour->ends_at, 0, 5) }}
                                                </div>

                                                @if ($workingHour->breaks->isNotEmpty())
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($workingHour->breaks as $break)
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-border bg-white px-2.5 py-1 text-xs text-muted">
                                                                {{ substr((string) $break->starts_at, 0, 5) }}–{{ substr((string) $break->ends_at, 0, 5) }}
                                                                @if ($break->label)
                                                                    · {{ $break->label }}
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>

                                            @if ($canManage)
                                                <div class="flex flex-wrap gap-2">
                                                    <details>
                                                        <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Edit</summary>

                                                        <form method="POST" action="{{ route('dashboard.booking.availability.working-hours.update', [$staff, $workingHour]) }}" class="mt-3 rounded-xl border border-border bg-white p-4 shadow-sm">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="grid gap-3 sm:grid-cols-3">
                                                                <select name="day_of_week" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                                    @foreach ($days as $day => $label)
                                                                        <option value="{{ $day }}" @selected((int) $workingHour->day_of_week === $day)>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <input name="starts_at" type="time" value="{{ substr((string) $workingHour->starts_at, 0, 5) }}" required class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                                <input name="ends_at" type="time" value="{{ substr((string) $workingHour->ends_at, 0, 5) }}" required class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                            </div>
                                                            <div class="mt-3 flex justify-end">
                                                                <x-dashboard.button size="sm" type="submit">{{ __('dashboard.booking_pages.save_hours') }}</x-dashboard.button>
                                                            </div>
                                                        </form>
                                                    </details>

                                                    <form method="POST" action="{{ route('dashboard.booking.availability.working-hours.destroy', [$staff, $workingHour]) }}" onsubmit="return confirm('Delete these working hours?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.booking_pages.delete') }}</x-dashboard.button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($canManage)
                                            <details class="mt-4">
                                                <summary class="cursor-pointer text-xs font-medium text-primary">{{ __('dashboard.booking_pages.add_break') }}</summary>
                                                <form method="POST" action="{{ route('dashboard.booking.availability.breaks.store', [$staff, $workingHour]) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_1.5fr_auto]">
                                                    @csrf
                                                    <input name="starts_at" type="time" required class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="ends_at" type="time" required class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="label" maxlength="255" :placeholder="__('dashboard.booking_pages.label')" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <x-dashboard.button size="sm" type="submit">{{ __('dashboard.booking_pages.add') }}</x-dashboard.button>
                                                </form>
                                            </details>

                                            @foreach ($workingHour->breaks as $break)
                                                <details class="mt-3">
                                                    <summary class="cursor-pointer text-xs font-medium text-muted">Edit {{ substr((string) $break->starts_at, 0, 5) }} break</summary>
                                                    <form method="POST" action="{{ route('dashboard.booking.availability.breaks.update', [$staff, $workingHour, $break]) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_1.5fr_auto_auto]">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input name="starts_at" type="time" value="{{ substr((string) $break->starts_at, 0, 5) }}" required class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <input name="ends_at" type="time" value="{{ substr((string) $break->ends_at, 0, 5) }}" required class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <input name="label" value="{{ $break->label }}" maxlength="255" :placeholder="__('dashboard.booking_pages.label')" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <x-dashboard.button size="sm" type="submit">Save</x-dashboard.button>
                                                    </form>
                                                    <form method="POST" action="{{ route('dashboard.booking.availability.breaks.destroy', [$staff, $workingHour, $break]) }}" class="mt-2 text-end" onsubmit="return confirm('Delete this break?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.booking_pages.delete_break') }}</x-dashboard.button>
                                                    </form>
                                                </details>
                                            @endforeach
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-dashed border-border p-6 text-sm text-muted">{{ __('dashboard.booking_pages.no_working_hours') }}</p>
                                @endforelse
                            </div>

                            @if ($canManage)
                                <details class="mt-4">
                                    <summary class="cursor-pointer text-sm font-medium text-primary">{{ __('dashboard.booking_pages.add_working_hours') }}</summary>
                                    <form method="POST" action="{{ route('dashboard.booking.availability.working-hours.store', $staff) }}" class="mt-3 rounded-xl border border-border bg-surface p-4">
                                        @csrf
                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <select name="day_of_week" class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                                @foreach ($days as $day => $label)
                                                    <option value="{{ $day }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input name="starts_at" type="time" value="09:00" required class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                            <input name="ends_at" type="time" value="17:00" required class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                            <x-dashboard.button size="sm" type="submit">{{ __('dashboard.booking_pages.add_hours') }}</x-dashboard.button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </section>
                    </div>

                    <aside>
                        <section>
                            <h3 class="font-medium text-secondary">{{ __('dashboard.booking_pages.time_off') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-muted">Blocked periods are accepted as timezone-aware ISO-8601 values and normalized by the backend.</p>

                            <div class="mt-3 space-y-3">
                                @forelse ($staff->timeOffs as $timeOff)
                                    <div class="rounded-xl border border-border bg-surface p-4">
                                        <div class="text-sm font-medium text-secondary">
                                            {{ $timeOff->starts_at?->format('d M Y, H:i') }}
                                            –
                                            {{ $timeOff->ends_at?->format('H:i') }}
                                        </div>

                                        @if ($timeOff->reason)
                                            <div class="mt-1 text-xs text-muted">{{ $timeOff->reason }}</div>
                                        @endif

                                        @if ($canManage)
                                            <details class="mt-3">
                                                <summary class="cursor-pointer text-xs font-medium text-primary">Edit</summary>
                                                <form method="POST" action="{{ route('dashboard.booking.availability.time-off.update', [$staff, $timeOff]) }}" class="mt-3 space-y-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input name="starts_at" value="{{ $timeOff->starts_at?->format('Y-m-d\TH:iP') }}" required class="block w-full rounded-lg border border-border bg-white px-3 py-2 text-xs">
                                                    <input name="ends_at" value="{{ $timeOff->ends_at?->format('Y-m-d\TH:iP') }}" required class="block w-full rounded-lg border border-border bg-white px-3 py-2 text-xs">
                                                    <input name="reason" value="{{ $timeOff->reason }}" maxlength="255" class="block w-full rounded-lg border border-border bg-white px-3 py-2 text-xs" :placeholder="__('dashboard.booking_pages.reason')">
                                                    <div class="flex justify-end">
                                                        <x-dashboard.button size="sm" type="submit">Save</x-dashboard.button>
                                                    </div>
                                                </form>
                                            </details>

                                            <form method="POST" action="{{ route('dashboard.booking.availability.time-off.destroy', [$staff, $timeOff]) }}" class="mt-2" onsubmit="return confirm('Delete this time off?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.booking_pages.delete') }}</x-dashboard.button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-dashed border-border p-6 text-sm text-muted">{{ __('dashboard.booking_pages.no_time_off') }}</p>
                                @endforelse
                            </div>

                            @if ($canManage)
                                <details class="mt-4">
                                    <summary class="cursor-pointer text-sm font-medium text-primary">{{ __('dashboard.booking_pages.add_time_off') }}</summary>
                                    <form method="POST" action="{{ route('dashboard.booking.availability.time-off.store', $staff) }}" class="mt-3 rounded-xl border border-border bg-surface p-4">
                                        @csrf
                                        <div class="space-y-3">
                                            <input name="starts_at" required :placeholder="__('dashboard.booking_pages.starts_at_placeholder')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                            <input name="ends_at" required :placeholder="__('dashboard.booking_pages.ends_at_placeholder')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                            <input name="reason" maxlength="255" :placeholder="__('dashboard.booking_pages.reason')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                            <x-dashboard.button size="sm" type="submit">{{ __('dashboard.booking_pages.add_time_off_action') }}</x-dashboard.button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </section>
                    </aside>
                </div>
            </x-dashboard.card>
        @empty
            <x-dashboard.card>
                <div class="px-6 py-12 text-center">
                    <h2 class="font-medium text-secondary">{{ __('dashboard.booking_pages.no_active_staff') }}</h2>
                    <p class="mt-2 text-sm text-muted">{{ __('dashboard.booking_pages.availability_staff_hint') }}</p>
                </div>
            </x-dashboard.card>
        @endforelse

        <x-dashboard.pagination :paginator="$staffMembers" />
    </div>
@endsection
