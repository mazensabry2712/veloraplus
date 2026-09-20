@extends('layouts.dashboard')

@section('title', 'Settings')
@section('heading', 'Settings')

@section('content')
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Company Preferences</h3>
                <p class="mt-1 text-sm text-slate-500">
                    These values are the company-level source of truth for locale, timezone, and default currency.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('dashboard.settings.preferences.update') }}" class="mt-5 space-y-5">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="default_currency">Default Currency</label>
                    <input id="default_currency" name="default_currency"
                           value="{{ old('default_currency', $tenant->default_currency) }}"
                           required maxlength="3"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm uppercase">
                    @error('default_currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="locale">Locale</label>
                    <input id="locale" name="locale"
                           value="{{ old('locale', $tenant->locale) }}"
                           required maxlength="10"
                           placeholder="en or en_US"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    @error('locale') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="timezone">Timezone</label>
                    <input id="timezone" name="timezone"
                           value="{{ old('timezone', $tenant->timezone) }}"
                           required
                           placeholder="Africa/Cairo"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Save Preferences
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Tenant Payment Integration</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Provider credentials are encrypted at rest and are never rendered back into this dashboard.
                </p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                {{ strtoupper($payment['provider']) }}
            </span>
        </div>

        <dl class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Account Reference</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $payment['account_reference'] ?? 'Not configured' }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $payment['status'] ? ucfirst($payment['status']) : 'Not configured' }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h3 class="text-base font-semibold text-slate-900">Update Payment Account</h3>
        <form method="POST" action="{{ route('dashboard.settings.payment.update') }}" class="mt-5 space-y-5">
            @csrf
            @method('PUT')

            <input type="hidden" name="provider" value="{{ $payment['provider'] }}">

            <div>
                <label class="block text-sm font-medium text-slate-700" for="account_reference">Account Reference</label>
                <input id="account_reference" name="account_reference" value="{{ old('account_reference', $payment['account_reference']) }}" required
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                @error('account_reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @foreach ([
                'merchant_id' => 'Merchant ID',
                'secret_key' => 'Secret Key',
                'payment_api_key' => 'Payment API Key',
                'merchant_redirect_url' => 'Merchant Redirect URL',
                'webhook_url' => 'Webhook URL',
            ] as $key => $label)
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="{{ $key }}">{{ $label }}</label>
                    <input id="{{ $key }}" name="credentials[{{ $key }}]" type="{{ str_contains($key, 'key') ? 'password' : 'text' }}"
                           value="{{ old('credentials.'.$key) }}"
                           autocomplete="new-password"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </div>
            @endforeach

            <div>
                <label class="block text-sm font-medium text-slate-700" for="status">Status</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Save Integration
            </button>
        </form>
    </section>
@endsection
