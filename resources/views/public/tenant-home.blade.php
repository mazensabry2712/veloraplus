<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-seo :seo="$seo" />
    </head>
    <body>
        <main>
            <h1>{{ $tenant->name }}</h1>

            @if ($tenant->industry)
                <p>{{ $tenant->industry }}</p>
            @endif

            @if ($tenant->country_code)
                <p>{{ $tenant->country_code }}</p>
            @endif

            <p>Public website powered by VeloraPlus.</p>
        </main>
    </body>
</html>
