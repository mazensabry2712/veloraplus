@props([
    'title',
    'description' => null,
])

@php
    $currentTenant = $tenant ?? app(\App\Domain\Tenancy\TenantContext::class)->current();
@endphp

<header class="dashboard-page-header">
    <div class="min-w-0">
        <p class="dashboard-eyebrow">{{ $currentTenant?->name }}</p>
        <div class="mt-1 flex min-w-0 items-center gap-3">
            <span class="dashboard-page-marker" aria-hidden="true"></span>
            <h1 class="dashboard-page-title">{{ $title }}</h1>
        </div>

        @if ($description)
            <p class="dashboard-page-description">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="dashboard-page-actions">
            {{ $actions }}
        </div>
    @endisset
</header>
