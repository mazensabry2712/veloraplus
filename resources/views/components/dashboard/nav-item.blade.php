@props([
    'label',
    'href' => null,
    'active' => false,
    'disabled' => false,
    'icon' => 'grid',
])

@php
    $classes = $active
        ? 'bg-primary text-white shadow-sm'
        : ($disabled
            ? 'cursor-not-allowed text-disabled'
            : 'text-muted hover:bg-primary/5 hover:text-primary');
@endphp

@if ($disabled || ! $href)
    <span
        aria-disabled="true"
        class="flex min-h-10 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $classes }}"
        {{ $attributes }}
    >
        <span class="flex min-w-0 items-center gap-3">
            <span class="h-4 w-4 shrink-0" aria-hidden="true">
                @include('components.dashboard.icon', ['name' => $icon])
            </span>
            <span class="truncate">{{ $label }}</span>
        </span>
        <span class="text-[11px] font-medium uppercase tracking-wide text-disabled">Soon</span>
    </span>
@else
    <a
        href="{{ $href }}"
        @if ($active) aria-current="page" @endif
        {{ $attributes->merge(['class' => "flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {$classes}"]) }}
    >
        <span class="h-4 w-4 shrink-0" aria-hidden="true">
            @include('components.dashboard.icon', ['name' => $icon])
        </span>
        <span class="truncate">{{ $label }}</span>
    </a>
@endif
