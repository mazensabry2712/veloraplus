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
                <a href="{{ $homeUrl }}">{{ $tenant->name }}</a>
                /
                <a href="{{ $servicesUrl }}">Services</a>
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
            </dl>
        </main>
    </body>
</html>
