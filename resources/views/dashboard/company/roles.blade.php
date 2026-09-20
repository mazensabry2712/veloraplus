@extends('layouts.dashboard')

@section('title', 'Roles & Permissions')
@section('heading', 'Roles & Permissions')

@section('content')
    <x-dashboard.page-header
        title="Roles & Permissions"
        description="Review tenant roles and manage custom permission sets without changing system roles."
    />

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-dashboard.card title="Tenant Roles" description="System roles are protected by the authorization contract; custom roles can be edited or removed when unused.">
            <div class="space-y-4">
                @forelse ($roles as $role)
                    @php
                        $systemRole = in_array($role->name, ['owner', 'admin', 'manager', 'staff', 'viewer'], true);
                        $rolePermissions = $role->permissions->pluck('name')->all();
                    @endphp

                    <article class="rounded-xl border border-border bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-secondary">{{ ucfirst($role->name) }}</h3>
                                    @if ($systemRole)
                                        <x-dashboard.badge>System</x-dashboard.badge>
                                    @else
                                        <x-dashboard.badge variant="info">Custom</x-dashboard.badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-muted">{{ count($rolePermissions) }} permission(s)</p>
                            </div>

                            @if (!$systemRole && auth()->user()->can('members.manage'))
                                <details>
                                    <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg border border-border px-3 text-xs font-medium text-secondary [&::-webkit-details-marker]:hidden">Edit</summary>
                                    <form method="POST" action="{{ route('company.roles.update', $role) }}" class="mt-3 w-[min(34rem,calc(100vw-3rem))] rounded-xl border border-border bg-surface p-4 shadow-sm">
                                        @csrf
                                        @method('PATCH')
                                        <p class="text-sm font-medium text-secondary">{{ $role->name }}</p>
                                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                            @foreach ($permissions as $permission)
                                                <label class="flex items-start gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $rolePermissions, true)) class="mt-1 rounded border-border text-primary focus:ring-primary">
                                                    <span class="text-secondary">{{ $permission }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="mt-4 flex justify-end">
                                            <x-dashboard.button size="sm" type="submit">Save Permissions</x-dashboard.button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse ($rolePermissions as $permission)
                                <span class="rounded-full border border-border bg-surface px-2.5 py-1 text-xs font-medium text-muted">{{ $permission }}</span>
                            @empty
                                <span class="text-sm text-muted">No permissions assigned.</span>
                            @endforelse
                        </div>

                        @if (!$systemRole && auth()->user()->can('members.manage'))
                            <div class="mt-4 flex justify-end border-t border-border pt-4">
                                <form method="POST" action="{{ route('company.roles.destroy', $role) }}" onsubmit="return confirm('Delete this custom role?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-dashboard.button variant="danger" size="sm" type="submit">Delete Role</x-dashboard.button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="py-10 text-center text-sm text-muted">No roles are configured for this tenant.</p>
                @endforelse
            </div>
        </x-dashboard.card>

        @if (auth()->user()->can('members.manage'))
            <x-dashboard.card title="Create Custom Role" description="Custom role names must not use reserved system role names.">
                <form method="POST" action="{{ route('company.roles.store') }}" class="space-y-4">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="100" placeholder="e.g. Front Desk" class="block w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">

                    <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                        @foreach ($permissions as $permission)
                            <label class="flex items-start gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', []), true)) class="mt-1 rounded border-border text-primary focus:ring-primary">
                                <span class="text-secondary">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>

                    <x-dashboard.button type="submit">Create Role</x-dashboard.button>
                </form>
            </x-dashboard.card>
        @endif
    </div>
@endsection
