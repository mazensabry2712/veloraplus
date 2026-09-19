<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $tenant->name }} — Dashboard</title>
        <meta name="robots" content="noindex,nofollow">
    </head>
    <body>
        <header>
            <div>
                <p>{{ $tenant->name }}</p>
                <span>{{ ucfirst($membership->role_key) }}</span>
            </div>

            <div>
                <span>{{ $account->name }}</span>
                <span>{{ $account->email }}</span>
            </div>
        </header>

        <nav aria-label="Company navigation">
            <a href="{{ url('/dashboard') }}">Dashboard</a>
            <span>Company</span>
            <span>Users</span>
            <span>Staff</span>
            <span>Customers</span>
            <span>Branches</span>
            <span>Booking</span>
            <span>Billing</span>
            <span>Module Marketplace</span>
            <span>Settings</span>
        </nav>

        <main>
            <section aria-labelledby="overview">
                <h1 id="overview">Dashboard</h1>
                <p>
                    {{ $today->format('l, F j, Y') }} · {{ $timezone }}
                </p>

                <div>
                    <article>
                        <h2>Customers</h2>
                        <strong>{{ number_format($usage['customers']) }}</strong>
                    </article>

                    <article>
                        <h2>Active staff</h2>
                        <strong>{{ number_format($usage['active_staff']) }}</strong>
                    </article>

                    <article>
                        <h2>Active services</h2>
                        <strong>{{ number_format($usage['active_services']) }}</strong>
                    </article>

                    <article>
                        <h2>Today's appointments</h2>
                        <strong>{{ number_format($usage['appointments_today']) }}</strong>
                    </article>

                    <article>
                        <h2>Waiting queue</h2>
                        <strong>{{ number_format($usage['waiting_queue_entries']) }}</strong>
                    </article>
                </div>
            </section>

            <section aria-labelledby="subscription">
                <h2 id="subscription">Subscription overview</h2>

                @if ($subscription)
                    <dl>
                        <dt>Status</dt>
                        <dd>{{ $subscription->status->value }}</dd>

                        <dt>Billing cycle</dt>
                        <dd>{{ $subscription->billing_cycle }}</dd>

                        <dt>Currency</dt>
                        <dd>{{ $subscription->currency }}</dd>

                        <dt>Period end</dt>
                        <dd>
                            {{ $subscription->current_period_end?->setTimezone($timezone)?->format('Y-m-d H:i') ?? '—' }}
                        </dd>
                    </dl>
                @else
                    <p>No subscription record is available yet.</p>
                @endif

                <p>Active entitlements: {{ number_format($activeEntitlements) }}</p>
            </section>

            <section aria-labelledby="usage">
                <h2 id="usage">Usage overview</h2>
                <p>
                    Dashboard usage figures are read from the current Tenant database and do not cross tenant boundaries.
                </p>
            </section>

            <section aria-labelledby="next">
                <h2 id="next">Module areas</h2>
                <p>
                    Booking is available through its existing public and application layers.
                    Company administration, Billing UI, Marketplace UI, and the detailed CRUD screens are delivered in the following Dashboard slices.
                </p>
            </section>
        </main>
    </body>
</html>
