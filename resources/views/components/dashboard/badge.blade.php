@props([
    'variant' => 'neutral',
])

@php
    $variantClasses = match ($variant) {
        'success' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200',
        'danger' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
        'info' => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
        default => 'bg-primary/5 text-secondary ring-1 ring-inset ring-primary/10',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {$variantClasses}"]) }}>
    {{ $slot }}
</span>
