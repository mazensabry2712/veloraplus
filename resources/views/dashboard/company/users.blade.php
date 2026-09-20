@extends('layouts.dashboard')

@section('title', __('dashboard.users'))
@section('heading', __('dashboard.users'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.users')"
        :description="__('dashboard.page_descriptions.users')"

    />

    <div class="mt-6 space-y-5">
        <x-dashboard.card :title="__('dashboard.company_pages.company_members')" :description="__('dashboard.company_pages.company_members_description')">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-primary/5">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.account') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.role') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.status') }}</th>
                        <th class="px-4 py-3">{{ __('dashboard.company_pages.joined') }}</th>
                        @if (auth()->user()->can('members.manage'))
                            <th class="px-4 py-3 text-end">{{ __('dashboard.company_pages.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($memberships as $membership)
                        <tr class="align-top transition-colors hover:bg-primary/5">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $membership->account?->name ?: __('dashboard.company_pages.unknown_account') }}</div>
                                <div class="mt-1 text-xs text-muted">{{ $membership->account?->email ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge>{{ ucfirst($membership->role_key) }}</x-dashboard.badge>
                            </td>
                            <td class="px-4 py-4">
                                <x-dashboard.badge :variant="$membership->status === 'active' ? 'success' : 'neutral'">
                                    {{ ucfirst($membership->status) }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-muted">{{ $membership->joined_at?->format('Y-m-d') ?: '—' }}</td>
                            @if (auth()->user()->can('members.manage'))
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <details>
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">{{ __('dashboard.company_pages.edit') }}</summary>
                                            <form method="POST" action="{{ route('company.users.update', $membership) }}" class="mt-3 w-[min(24rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                                @csrf
                                                @method('PATCH')
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-muted" for="role_{{ $membership->id }}">Role</label>
                                                <select id="role_{{ $membership->id }}" name="role_key" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role }}" @selected($membership->role_key === $role)>{{ ucfirst($role) }}</option>
                                                    @endforeach
                                                </select>

                                                <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-muted" for="status_{{ $membership->id }}">Status</label>
                                                <select id="status_{{ $membership->id }}" name="status" class="mt-2 block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <option value="active" @selected($membership->status === 'active')>Active</option>
                                                    <option value="inactive" @selected($membership->status === 'inactive')>Inactive</option>
                                                </select>

                                                <div class="mt-4 flex justify-end">
                                                    <x-dashboard.button size="sm" type="submit">{{ __('dashboard.company_pages.save') }}</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        @if ($membership->status === 'active')
                                            <form method="POST" action="{{ route('company.users.destroy', $membership) }}" onsubmit="return confirm(@js(__('dashboard.company_pages.deactivate_membership_confirm')));">
                                                @csrf
                                                @method('DELETE')
                                                <x-dashboard.button variant="danger" size="sm" type="submit">{{ __('dashboard.deactivate') }}</x-dashboard.button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-muted">{{ __('dashboard.company_pages.no_members') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$memberships" />
        </x-dashboard.card>

        @if (auth()->user()->can('members.manage'))
            <details class="group" @if ($errors->any()) open @endif>
                <summary class="inline-flex min-h-10 w-full cursor-pointer list-none items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm font-semibold text-secondary transition-colors hover:border-primary/30 hover:bg-primary/5 [&::-webkit-details-marker]:hidden sm:w-auto">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">@include('components.dashboard.icon', ['name' => 'users'])</span>
                        {{ __('dashboard.company_pages.add_user') }}
                    </span>
                    <span class="text-muted transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
                </summary>
                <div class="mt-4">
                    <x-dashboard.card :title="__('dashboard.company_pages.add_user')" :description="__('dashboard.company_pages.add_user_description')">
                        <form method="POST" action="{{ route('company.users.store') }}" class="space-y-4">
                            @csrf
                            <input name="email" type="email" value="{{ old('email') }}" required maxlength="190" :placeholder="__('dashboard.company_pages.platform_account_email')" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                            <select name="role_key" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}" @selected(old('role_key', 'staff') === $role)>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            <x-dashboard.button type="submit">{{ __('dashboard.company_pages.add_user') }}</x-dashboard.button>
                        </form>
                    </x-dashboard.card>
                </div>
            </details>
        @endif
    </div>
@endsection
