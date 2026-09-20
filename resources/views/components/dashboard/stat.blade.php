@props([
    'label',
    'value',
    'detail' => null,
    'icon' => 'chart',
    'href' => null,
])

@php
    $classes = 'group block rounded-2xl border border-border bg-white p-5 shadow-sm transition-all duration-150';

    if ($href) {
        $classes .= ' hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
@endif
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.11em] text-muted">{{ $label }}</p>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-secondary sm:text-3xl">{{ $value }}</p>
            </div>

            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-inset ring-primary/10" aria-hidden="true">
                @include('components.dashboard.icon', ['name' => $icon])
            </span>
        </div>

        @if ($detail)
            <p class="mt-3 text-sm leading-6 text-muted">{{ $detail }}</p>
        @endif

        @if ($href)
            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary">
                Open workspace
                <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">→</span>
            </span>
        @endif
@if ($href)
    </a>
@else
    </div>
@endif
