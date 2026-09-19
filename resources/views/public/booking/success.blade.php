<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-seo :seo="$seo" />
    </head>
    <body>
        <main>
            <h1>Booking confirmed</h1>
            <p>
                Your booking for {{ $service->name }} with {{ $tenant->name }} has been confirmed.
            </p>
            <p>Customer: {{ $appointment->customer?->name }}</p>
            <p>Appointment reference: {{ $appointment->getKey() }}</p>
            <a href="{{ $serviceUrl }}">Back to service</a>
        </main>
    </body>
</html>
