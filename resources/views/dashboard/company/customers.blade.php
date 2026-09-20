@extends('layouts.dashboard')

@section('title', __('dashboard.customers'))
@section('heading', __('dashboard.customers'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.customers')"
        :description="__('dashboard.page_descriptions.customers')"
    />

    <div class="mt-6 space-y-5">
        <x-dashboard.card :title="__('dashboard.company_pages.customer_list')" :description="__('dashboard.company_pages.customer_list_description')">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-primary/5">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.customer') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.contact') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.source') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.status') }}</th>
                        @if (auth()->user()->can('customers.manage'))
                            <th class="px-4 py-3 text-end">{{ __('dashboard.company_pages.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($customers as $customer)
                        <tr class="align-top transition-colors hover:bg-primary/5">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $customer->name }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $customer->email ?: __('dashboard.company_pages.no_email') }}</div>
                            </td>
                            <td class="px-4 py-4 text-muted whitespace-nowrap">{{ $customer->phone ?: '—' }}</td>
                            <td class="px-4 py-4 text-muted">{{ $customer->source ?: '—' }}</td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$customer->status === 'active' ? 'success' : 'neutral'">
                                    {{ ucfirst($customer->status) }}
                                </x-dashboard.badge>
                            </td>
                            @if (auth()->user()->can('customers.manage'))
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <details>
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">{{ __('dashboard.company_pages.edit') }}</summary>
                                            <form method="POST" action="{{ route('company.customers.update', $customer) }}" class="mt-3 w-[min(30rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <input name="name" value="{{ $customer->name }}" required maxlength="150" placeholder="Name" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="phone" value="{{ $customer->phone }}" maxlength="50" placeholder="Phone" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="email" type="email" value="{{ $customer->email }}" maxlength="190" placeholder="Email" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input name="source" value="{{ $customer->source }}" maxlength="50" placeholder="Source" class="rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <select name="status" class="sm:col-span-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                        <option value="active" @selected($customer->status === 'active')>Active</option>
                                                        <option value="inactive" @selected($customer->status === 'inactive')>Inactive</option>
                                                    </select>
                                                    <textarea name="notes" rows="3" :placeholder="__('dashboard.company_pages.notes')" class="sm:col-span-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">{{ $customer->notes }}</textarea>
                                                </div>
                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">{{ __('dashboard.company_pages.save') }}</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('company.customers.destroy', $customer) }}" onsubmit="return confirm(@js(__('dashboard.company_pages.archive_customer_confirm')));">
                                            @csrf
                                            @method('DELETE')
                                            <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.company_pages.archive') }}</x-dashboard.button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-muted">{{ __('dashboard.company_pages.no_customers') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$customers" />
        </x-dashboard.card>

        @if (auth()->user()->can('customers.manage'))
            <details class="group" @if ($errors->any()) open @endif>
                <summary class="inline-flex min-h-10 w-full cursor-pointer list-none items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm font-semibold text-secondary transition-colors hover:border-primary/30 hover:bg-primary/5 [&::-webkit-details-marker]:hidden sm:w-auto">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'user'])</span>
                        {{ __('dashboard.company_pages.add_customer') }}
                    </span>
                    <span class="text-muted transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
                </summary>
                <div class="mt-4">
                    <x-dashboard.card :title="__('dashboard.company_pages.add_customer')" :description="__('dashboard.company_pages.add_customer_description')">

                <form method="POST" action="{{ route('company.customers.store') }}" class="space-y-4">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="150" :placeholder="__('dashboard.company_pages.customer_name')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="phone" value="{{ old('phone') }}" maxlength="50" placeholder="Phone" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="email" type="email" value="{{ old('email') }}" maxlength="190" placeholder="Email" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <input name="source" value="{{ old('source') }}" maxlength="50" :placeholder="__('dashboard.company_pages.source_optional')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <select name="status" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    <textarea name="notes" rows="4" :placeholder="__('dashboard.company_pages.notes')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">{{ old('notes') }}</textarea>
                    <x-dashboard.button type="submit">{{ __('dashboard.company_pages.create_customer') }}</x-dashboard.button>
                </form>
                    </x-dashboard.card>
                </div>
            </details>
        @endif
    </div>
@endsection
