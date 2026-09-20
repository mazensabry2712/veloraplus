@props([
    'label',
    'href' => null,
    'active' => false,
    'disabled' => false,
    'icon' => 'grid',
])

@php
    $classes = $active
        ? 'dashboard-nav-link dashboard-nav-link-active'
        : ($disabled
            ? 'dashboard-nav-link dashboard-nav-link-disabled'
            : 'dashboard-nav-link');
@endphp

@if ($disabled || ! $href)
    <span
        aria-disabled="true"
        class="{{ $classes }}"
        {{ $attributes }}
    >
        <span class="dashboard-nav-content">
            <span class="dashboard-nav-icon" aria-hidden="true">
                @include('components.dashboard.icon', ['name' => $icon])
            </span>
            <span class="truncate">{{ $label }}</span>
        </span>
        <span class="dashboard-nav-soon">Soon</span>
    </span>
@else
    <a
        href="{{ $href }}"
        @if ($active) aria-current="page" @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >
        <span class="dashboard-nav-content">
            <span class="dashboard-nav-icon" aria-hidden="true">
                @include('components.dashboard.icon', ['name' => $icon])
            </span>
            <span class="truncate">{{ $label }}</span>
        </span>
    </a>
@endif
