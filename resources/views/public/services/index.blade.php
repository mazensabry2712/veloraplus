<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-seo :seo="$seo" />
    </head>
    <body>
        <main>
            <h1>Services at {{ $tenant->name }}</h1>

            @if ($services->isEmpty())
                <p>No public services are currently available.</p>
            @else
                <ul>
                    @foreach ($services as $service)
                        <li>
                            <a href="{{ route('public.services.show', ['slug' => $service->slug]) }}">
                                {{ $service->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </main>
    </body>
</html>
