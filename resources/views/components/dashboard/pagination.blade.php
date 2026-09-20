<div class="mt-5 flex items-center justify-between gap-3 text-sm">
    <p class="text-muted">
        Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
    </p>

    <div class="flex items-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-9 items-center rounded-lg border border-border px-3 text-muted" aria-disabled="true">Previous</span>
        @else
            <a
                href="{{ $paginator->previousPageUrl() }}"
                rel="prev"
                class="inline-flex min-h-9 items-center rounded-lg border border-border bg-white px-3 font-medium text-secondary hover:bg-surface"
            >Previous</a>
        @endif

        @if ($paginator->hasMorePages())
            <a
                href="{{ $paginator->nextPageUrl() }}"
                rel="next"
                class="inline-flex min-h-9 items-center rounded-lg border border-border bg-white px-3 font-medium text-secondary hover:bg-surface"
            >Next</a>
        @else
            <span class="inline-flex min-h-9 items-center rounded-lg border border-border px-3 text-muted" aria-disabled="true">Next</span>
        @endif
    </div>
</div>
