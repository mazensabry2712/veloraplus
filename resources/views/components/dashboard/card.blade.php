@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-white']) }}>
    @if ($title || $description || isset($header))
        <header class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:px-6">
            @if ($title)
                <h2 class="text-base font-semibold text-secondary">{{ $title }}</h2>
            @endif

            @if ($description)
                <p class="text-sm leading-6 text-muted">{{ $description }}</p>
            @endif

            {{ $header ?? '' }}
        </header>
    @endif

    <div class="p-5 sm:p-6">
        {{ $slot }}
    </div>
</section>
