@extends('layouts.dashboard')

@section('title', 'Users')
@section('heading', 'Users')

@section('content')
    <x-dashboard.page-header
        title="Users"
        description="Manage platform accounts that have access to the current company and control their tenant role."
    />

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-dashboard.card title="Company Members" description="Memberships are read from the central identity store and filtered to the active tenant.">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-surface">
                    <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Joined</th>
                        @if (auth()->user()->can('members.manage'))
                            <th class="px-4 py-3 text-end">Actions</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @forelse ($memberships as $membership)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-medium text-secondary">{{ $membership->account?->name ?: 'Unknown account' }}</div>
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
                                            <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Edit</summary>
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
                                                    <x-dashboard.button size="sm" type="submit">Save</x-dashboard.button>
                                                </div>
                                            </form>
                                        </details>

                                        @if ($membership->status === 'active')
                                            <form method="POST" action="{{ route('company.users.destroy', $membership) }}" onsubmit="return confirm('Deactivate this membership?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-dashboard.button variant="danger" size="sm" type="submit">Deactivate</x-dashboard.button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-muted">No company members found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$memberships" />
        </x-dashboard.card>

        @if (auth()->user()->can('members.manage'))
            <x-dashboard.card title="Add User" description="The account must already exist on the VeloraPlus platform.">
                <form method="POST" action="{{ route('company.users.store') }}" class="space-y-4">
                    @csrf
                    <input name="email" type="email" value="{{ old('email') }}" required maxlength="190" placeholder="Platform account email" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                    <select name="role_key" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role_key', 'staff') === $role)>{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                    <x-dashboard.button type="submit">Add User</x-dashboard.button>
                </form>
            </x-dashboard.card>
        @endif
    </div>
@endsection
