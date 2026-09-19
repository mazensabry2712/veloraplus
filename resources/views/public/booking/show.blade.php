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
                <a href="{{ $serviceUrl }}">{{ $tenant->name }} — {{ $service->name }}</a>
            </nav>

            <h1>Book {{ $service->name }}</h1>

            <p>
                Start a booking request with {{ $tenant->name }}.
            </p>

            <a href="{{ $serviceUrl }}">Back to service</a>
        </main>
    </body>
</html>
