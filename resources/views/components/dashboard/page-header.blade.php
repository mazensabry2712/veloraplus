@props([
    'title',
    'description' => null,
])

<header class="flex flex-col gap-4 rounded-2xl border border-primary/10 bg-primary/5 px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6">
    <div class="min-w-0">
        <div class="flex min-w-0 items-start gap-3"><span class="mt-1.5 h-8 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span><div class="min-w-0"><h1 class="text-2xl font-semibold tracking-tight text-secondary sm:text-3xl">{{ $title }}</h1></div></div>

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
