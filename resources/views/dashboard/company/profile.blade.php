@extends('layouts.dashboard')

@section('title', __('dashboard.profile'))
@section('heading', __('dashboard.profile'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.profile')"
        :description="__('dashboard.page_descriptions.profile')"

    />

    <div class="mt-6">
        <x-dashboard.card
            title="Company Information"
            description="Changes are persisted to the current tenant only."
        >
            @if (auth()->user()->can('company.update'))
                <form method="POST" action="{{ route('company.profile.update') }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="name" class="block text-sm font-medium text-secondary">Company Name</label>
                            <input id="name" name="name" value="{{ old('name', $tenant->name) }}" required maxlength="255" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                            @error('name') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="legal_name" class="block text-sm font-medium text-secondary">Legal Name</label>
                            <input id="legal_name" name="legal_name" value="{{ old('legal_name', $tenant->legal_name) }}" maxlength="255" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                            @error('legal_name') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="industry" class="block text-sm font-medium text-secondary">Industry</label>
                            <input id="industry" name="industry" value="{{ old('industry', $tenant->industry) }}" maxlength="255" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                            @error('industry') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="business_type" class="block text-sm font-medium text-secondary">Business Type</label>
                            <input id="business_type" name="business_type" value="{{ old('business_type', $tenant->business_type) }}" maxlength="255" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                            @error('business_type') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="border-t border-border pt-6">
                        <h3 class="text-sm font-semibold text-secondary">Localization</h3>
                        <div class="mt-4 grid gap-5 md:grid-cols-3">
                            <div>
                                <label for="country_code" class="block text-sm font-medium text-secondary">Country Code</label>
                                <input id="country_code" name="country_code" value="{{ old('country_code', $tenant->country_code) }}" maxlength="2" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('country_code') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="default_currency" class="block text-sm font-medium text-secondary">Default Currency</label>
                                <input id="default_currency" name="default_currency" value="{{ old('default_currency', $tenant->default_currency) }}" required maxlength="3" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm uppercase text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('default_currency') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="locale" class="block text-sm font-medium text-secondary">Locale</label>
                                <input id="locale" name="locale" value="{{ old('locale', $tenant->locale) }}" required maxlength="10" placeholder="en or ar_EG" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('locale') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <label for="timezone" class="block text-sm font-medium text-secondary">Timezone</label>
                                <input id="timezone" name="timezone" value="{{ old('timezone', $tenant->timezone) }}" required placeholder="Africa/Cairo" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('timezone') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-border pt-6">
                        <h3 class="text-sm font-semibold text-secondary">Contact & Address</h3>
                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-secondary">Phone</label>
                                <input id="phone" name="phone" value="{{ old('phone', $tenant->phone) }}" maxlength="50" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('phone') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-secondary">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $tenant->email) }}" maxlength="255" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('email') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="website" class="block text-sm font-medium text-secondary">Website</label>
                                <input id="website" name="website" type="url" value="{{ old('website', $tenant->website) }}" maxlength="2048" placeholder="https://example.com" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('website') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="city" class="block text-sm font-medium text-secondary">City</label>
                                <input id="city" name="city" value="{{ old('city', $tenant->city) }}" maxlength="255" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @error('city') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-secondary">Address</label>
                                <textarea id="address" name="address" rows="4" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-secondary focus:border-primary focus:ring-2 focus:ring-primary/15">{{ old('address', $tenant->address) }}</textarea>
                                @error('address') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <x-dashboard.button type="submit">Save Company Profile</x-dashboard.button>
                    </div>
                </form>
            @else
                <dl class="grid gap-5 md:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Company</dt><dd class="mt-1 text-sm font-medium text-secondary">{{ $tenant->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Legal Name</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->legal_name ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Industry</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->industry ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Business Type</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->business_type ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Currency</dt><dd class="mt-1 text-sm font-medium text-secondary">{{ $tenant->default_currency }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Timezone</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->timezone }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Locale</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->locale }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Country</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->country_code ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Email</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->email ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Phone</dt><dd class="mt-1 text-sm text-secondary">{{ $tenant->phone ?: '—' }}</dd></div>
                </dl>
            @endif
        </x-dashboard.card>
    </div>
@endsection
