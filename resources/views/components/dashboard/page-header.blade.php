@props([
    'title',
    'description' => null,
])

<header class="flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-secondary sm:text-3xl">{{ $title }}</h1>

        @if ($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
