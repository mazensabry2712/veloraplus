@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit',
    'disabled' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-50';
    $sizeClasses = match ($size) {
        'sm' => 'min-h-9 px-3 text-xs',
        'lg' => 'min-h-11 px-5 text-sm',
        default => 'min-h-10 px-4 text-sm',
    };
    $variantClasses = match ($variant) {
        'secondary' => 'border border-border bg-white text-secondary hover:bg-surface focus-visible:outline-primary',
        'ghost' => 'text-muted hover:bg-surface hover:text-secondary focus-visible:outline-primary',
        'danger' => 'bg-red-700 text-white hover:bg-red-800 focus-visible:outline-red-700',
        default => 'bg-primary text-white hover:bg-primary-dark focus-visible:outline-primary',
    };
@endphp

<button
    type="{{ $type }}"
    @disabled($disabled)
    {{ $attributes->merge(['class' => "{$baseClasses} {$sizeClasses} {$variantClasses}"]) }}
>
    {{ $slot }}
</button>
