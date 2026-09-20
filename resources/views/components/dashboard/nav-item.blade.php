@props([
    'label',
    'href' => null,
    'active' => false,
    'disabled' => false,
])

@php
    $classes = $active
        ? 'bg-primary text-white shadow-sm'
        : ($disabled
            ? 'cursor-not-allowed text-disabled'
            : 'text-muted hover:bg-surface hover:text-secondary');
@endphp

@if ($disabled || ! $href)
    <span
        aria-disabled="true"
        class="flex min-h-10 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $classes }}"
        {{ $attributes }}
    >
        <span>{{ $label }}</span>
        <span class="text-[11px] font-medium uppercase tracking-wide text-disabled">Soon</span>
    </span>
@else
    <a
        href="{{ $href }}"
        @if ($active) aria-current="page" @endif
        {{ $attributes->merge(['class' => "flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-medium {$classes}"]) }}
    >
        {{ $label }}
    </a>
@endif
