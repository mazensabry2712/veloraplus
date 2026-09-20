@extends('layouts.dashboard')

@section('title', __('dashboard.usage'))
@section('heading', __('dashboard.usage'))

@section('content')
    <x-dashboard.page-header
        :title="__('dashboard.usage')"
        :description="__('dashboard.page_descriptions.usage')"
    />

    <div class="mt-6">
        <x-dashboard.card>
            <p class="text-sm leading-6 text-muted">
                Current entitlement limits for
                <span class="font-semibold text-secondary">{{ $tenant->name }}</span>.
                No runtime counters are invented where the platform does not expose usage metering.
            </p>
        </x-dashboard.card>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-border bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-surface">
                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted">
                    <th class="px-4 py-3">Capability</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Limit</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Ends</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-border">
                @forelse ($limits as $row)
                    <tr class="align-top">
                        <td class="px-4 py-4">
                            <div class="font-medium text-secondary">{{ $row['name'] }}</div>
                            <div class="mt-1 text-xs text-muted">{{ $row['key'] }}</div>
                        </td>
                        <td class="px-4 py-4 text-muted">{{ ucfirst($row['type']) }}</td>
                        <td class="px-4 py-4">
                            <x-dashboard.badge
                                :variant="in_array($row['status'], ['active', 'enabled'], true) ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'neutral')"
                            >
                                {{ str_replace('_', ' ', ucfirst($row['status'])) }}
                            </x-dashboard.badge>
                        </td>
                        <td class="px-4 py-4 text-muted">{{ $row['limit'] === null ? 'Unlimited / not defined' : $row['limit'] }}</td>
                        <td class="px-4 py-4 text-muted">{{ ucfirst($row['source']) }}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-muted">{{ $row['ends_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-muted">No tenant entitlements are active.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
