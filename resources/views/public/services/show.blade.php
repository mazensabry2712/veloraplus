<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-seo :seo="$seo" />
    </head>
    <body>
        <main>
            <nav aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}">{{ $tenant->name }}</a>
                /
                <a href="{{ route('public.services.index') }}">Services</a>
                /
                <span>{{ $service->name }}</span>
            </nav>

            <h1>{{ $service->name }}</h1>

            @if ($service->description)
                <p>{{ $service->description }}</p>
            @endif

            <dl>
                <dt>Duration</dt>
                <dd>{{ $service->duration_minutes }} minutes</dd>
                <dt>Price</dt>
                <dd>{{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}</dd>
            </dl>

            <a href="{{ url('/book/'.$service->slug) }}">Book this service</a>
        </main>
    </body>
</html>
