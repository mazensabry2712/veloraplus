<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if ($branding['favicon_url'])
            <link rel="icon" href="{{ $branding['favicon_url'] }}">
        @endif

        <style>
            :root {
                --brand-primary: {{ $branding['primary_color'] ?: '#1D4ED8' }};
                --brand-secondary: {{ $branding['secondary_color'] ?: '#0F172A' }};
                --brand-accent: {{ $branding['accent_color'] ?: '#F59E0B' }};
                --brand-background: {{ $branding['background_color'] ?: '#FFFFFF' }};
                --brand-text: {{ $branding['text_color'] ?: '#0F172A' }};
            }

            body {
                margin: 0;
                background: var(--brand-background);
                color: var(--brand-text);
                font-family: system-ui, sans-serif;
            }

            a {
                color: var(--brand-primary);
            }

            .brand-primary {
                color: var(--brand-primary);
            }

            .brand-secondary {
                color: var(--brand-secondary);
            }

            h1 {
                color: var(--brand-secondary);
            }

            .brand-button {
                display: inline-block;
                padding: 0.6rem 1rem;
                border-radius: 0.5rem;
                background: var(--brand-primary);
                color: #fff;
                text-decoration: none;
            }

            .social-links {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .social-link {
                display: inline-block;
                padding: 0.45rem 0.75rem;
                border: 1px solid var(--brand-primary);
                border-radius: 999px;
                text-decoration: none;
            }
        </style>

        <x-seo :seo="$seo" />
    </head>
    <body>
        <main style="max-width: 960px; margin: 0 auto; padding: 2rem 1rem;">
            @if ($branding['logo_url'])
                <img
                    src="{{ $branding['logo_url'] }}"
                    alt="{{ $tenant->name }} logo"
                    style="max-width: 220px; max-height: 100px; object-fit: contain;"
                >
            @endif

            <h1>{{ $tenant->name }}</h1>

            @if ($tenant->industry)
                <p>{{ $tenant->industry }}</p>
            @endif

            @if ($tenant->business_type)
                <p>{{ $tenant->business_type }}</p>
            @endif

            @if ($tenant->country_code || $tenant->city || $tenant->address)
                <address>
                    @if ($tenant->address)
                        <div>{{ $tenant->address }}</div>
                    @endif
                    @if ($tenant->city || $tenant->country_code)
                        <div>{{ $tenant->city }}{{ $tenant->city && $tenant->country_code ? ', ' : '' }}{{ $tenant->country_code }}</div>
                    @endif
                </address>
            @endif

            @if ($tenant->phone)
                <p><a href="tel:{{ $tenant->phone }}">{{ $tenant->phone }}</a></p>
            @endif

            @if ($tenant->email)
                <p><a href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</a></p>
            @endif

            @if ($tenant->website)
                <p><a class="brand-button" href="{{ $tenant->website }}" target="_blank" rel="noopener noreferrer">Website</a></p>
            @endif

            @php
                $socialLabels = [
                    'facebook' => 'Facebook',
                    'instagram' => 'Instagram',
                    'linkedin' => 'LinkedIn',
                    'youtube' => 'YouTube',
                    'tiktok' => 'TikTok',
                    'x' => 'X',
                    'whatsapp' => 'WhatsApp',
                ];
            @endphp

            @if (collect($social)->filter()->isNotEmpty())
                <section aria-label="Social media">
                    <h2 class="brand-primary">Follow {{ $tenant->name }}</h2>

                    <div class="social-links">
                        @foreach ($socialLabels as $key => $label)
                            @if (!empty($social[$key]))
                                <a
                                    class="social-link"
                                    href="{{ $social[$key] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ $label }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            <p>Public website powered by VeloraPlus.</p>
        </main>
    </body>
</html>
