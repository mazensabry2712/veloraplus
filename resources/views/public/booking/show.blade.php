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

            @if ($service->description)
                <p>{{ $service->description }}</p>
            @endif

            <p>
                {{ $service->duration_minutes }} minutes ·
                {{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}
            </p>

            @if ($staff === [])
                <p>No staff member is currently available for this service.</p>
            @else
                <form method="POST" action="{{ route('public.booking.store', ['slug' => $service->slug]) }}">
                    @csrf

                    <div>
                        <label for="customer_name">Name</label>
                        <input id="customer_name" name="customer_name" type="text" maxlength="150" required value="{{ old('customer_name') }}">
                        @error('customer_name')
                            <p>{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="customer_phone">Phone</label>
                        <input id="customer_phone" name="customer_phone" type="tel" maxlength="50" required value="{{ old('customer_phone') }}">
                        @error('customer_phone')
                            <p>{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="customer_email">Email</label>
                        <input id="customer_email" name="customer_email" type="email" maxlength="190" value="{{ old('customer_email') }}">
                        @error('customer_email')
                            <p>{{ $message }}</p>
                        @enderror
                    </div>

                    @if (count($staff) === 1)
                        <input type="hidden" name="staff_id" value="{{ $staff[0]->getKey() }}">
                        <p>Staff: {{ $staff[0]->name }}</p>
                    @else
                        <div>
                            <label for="staff_id">Staff</label>
                            <select id="staff_id" name="staff_id" required>
                                <option value="">Select staff</option>
                                @foreach ($staff as $member)
                                    <option value="{{ $member->getKey() }}" @selected(old('staff_id') === $member->getKey())>
                                        {{ $member->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('staff_id')
                                <p>{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div>
                        <label for="starts_at">Date and time</label>
                        <input
                            id="starts_at"
                            name="starts_at"
                            type="datetime-local"
                            required
                            value="{{ old('starts_at') }}"
                        >
                        <small>
                            Choose a time in the selected staff member's location timezone.
                        </small>
                        @error('starts_at')
                            <p>{{ $message }}</p>
                        @enderror
                    </div>

                    <input
                        type="hidden"
                        name="idempotency_key"
                        value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::ulid()) }}"
                    >

                    <button type="submit">
                        {{ $service->price_minor > 0 ? 'Continue to payment' : 'Confirm booking' }}
                    </button>
                </form>
            @endif

            <a href="{{ $serviceUrl }}">Back to service</a>
        </main>
    </body>
</html>
