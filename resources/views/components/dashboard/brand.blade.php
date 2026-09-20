@props([
    'tenant' => null,
    'branding' => null,
])

@php
    $tenantName = $tenant?->name ?: __('dashboard.company');
    $logoUrl = is_array($branding) ? ($branding['logo_url'] ?? null) : null;
@endphp

<a href="{{ route('dashboard') }}"
   class="inline-flex min-w-0 items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
   aria-label="{{ $tenantName }} {{ __('dashboard.dashboard') }}">
    @if ($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt="{{ $tenantName }}"
            class="h-9 max-w-[13rem] w-auto object-contain"
            decoding="async"
        >
    @else
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-white shadow-sm" aria-hidden="true">
            {{ str($tenantName)->substr(0, 1)->upper() }}
        </span>
        <span class="min-w-0 truncate text-base font-semibold tracking-tight text-secondary">{{ $tenantName }}</span>
    @endif
</a>
