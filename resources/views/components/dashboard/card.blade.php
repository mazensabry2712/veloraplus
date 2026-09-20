@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'dashboard-panel']) }}>
    @if ($title || $description || isset($header))
        <header class="dashboard-panel-header">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="dashboard-panel-title">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="dashboard-panel-description">{{ $description }}</p>
                @endif
            </div>

            @isset($header)
                <div class="dashboard-panel-actions">
                    {{ $header }}
                </div>
            @endisset
        </header>
    @endif

    <div class="dashboard-panel-body">
        {{ $slot }}
    </div>
</section>
