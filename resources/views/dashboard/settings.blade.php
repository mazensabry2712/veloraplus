@extends('layouts.dashboard')

@section('title', __('dashboard.settings'))
@section('heading', __('dashboard.settings'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.settings')"
        :description="__('dashboard.page_descriptions.settings')"
    />

    <div class="mt-6 space-y-6">
        <x-dashboard.card
            title="Company Preferences"
            description="These values are the company-level source of truth for locale, timezone, and default currency."
        >
            <form method="POST" action="{{ route('dashboard.settings.preferences.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-5 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-secondary" for="default_currency">Default Currency</label>
                        <input
                            id="default_currency"
                            name="default_currency"
                            value="{{ old('default_currency', $tenant->default_currency) }}"
                            required
                            maxlength="3"
                            autocomplete="off"
                            class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase text-secondary placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/15"
                        >
                        @error('default_currency')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary" for="locale">Locale</label>
                        <input
                            id="locale"
                            name="locale"
                            value="{{ old('locale', $tenant->locale) }}"
                            required
                            maxlength="10"
                            placeholder="en or en_US"
                            autocomplete="off"
                            class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/15"
                        >
                        @error('locale')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary" for="timezone">Timezone</label>
                        <input
                            id="timezone"
                            name="timezone"
                            value="{{ old('timezone', $tenant->timezone) }}"
                            required
                            placeholder="Africa/Cairo"
                            autocomplete="off"
                            class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/15"
                        >
                        @error('timezone')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-dashboard.button type="submit">Save Preferences</x-dashboard.button>
            </form>
        </x-dashboard.card>

        <x-dashboard.card
            title="Tenant Payment Integration"
            description="Provider credentials are encrypted at rest and are never rendered back into this dashboard."
        >
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-secondary">Provider</span>
                <x-dashboard.badge>{{ strtoupper($payment['provider']) }}</x-dashboard.badge>
                @if ($payment['status'])
                    <x-dashboard.badge :variant="$payment['status'] === 'active' ? 'success' : 'neutral'">
                        {{ ucfirst($payment['status']) }}
                    </x-dashboard.badge>
                @endif
            </div>

            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-border bg-surface p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Account Reference</dt>
                    <dd class="mt-1 font-medium text-secondary">{{ $payment['account_reference'] ?? 'Not configured' }}</dd>
                </div>

                <div class="rounded-lg border border-border bg-surface p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Status</dt>
                    <dd class="mt-1 font-medium text-secondary">{{ $payment['status'] ? ucfirst($payment['status']) : 'Not configured' }}</dd>
                </div>
            </dl>
        </x-dashboard.card>

        <x-dashboard.card
            title="Update Payment Account"
            description="Leave a credential field empty when the current stored value should remain unchanged."
        >
            <form method="POST" action="{{ route('dashboard.settings.payment.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <input type="hidden" name="provider" value="{{ $payment['provider'] }}">

                <div>
                    <label class="block text-sm font-medium text-secondary" for="account_reference">Account Reference</label>
                    <input
                        id="account_reference"
                        name="account_reference"
                        value="{{ old('account_reference', $payment['account_reference']) }}"
                        required
                        autocomplete="off"
                        class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/15"
                    >
                    @error('account_reference')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ([
                        'merchant_id' => 'Merchant ID',
                        'secret_key' => 'Secret Key',
                        'payment_api_key' => 'Payment API Key',
                        'merchant_redirect_url' => 'Merchant Redirect URL',
                        'webhook_url' => 'Webhook URL',
                    ] as $key => $label)
                        <div>
                            <label class="block text-sm font-medium text-secondary" for="{{ $key }}">{{ $label }}</label>
                            <input
                                id="{{ $key }}"
                                name="credentials[{{ $key }}]"
                                type="{{ str_contains($key, 'key') ? 'password' : 'text' }}"
                                value="{{ old('credentials.'.$key) }}"
                                autocomplete="new-password"
                                class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/15"
                            >
                        </div>
                    @endforeach
                </div>

                <div class="max-w-sm">
                    <label class="block text-sm font-medium text-secondary" for="status">Status</label>
                    <select
                        id="status"
                        name="status"
                        class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15"
                    >
                        <option value="active" @selected(old('status', $payment['status']) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $payment['status']) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <x-dashboard.button type="submit">Save Integration</x-dashboard.button>
            </form>
        </x-dashboard.card>
    </div>
@endsection
