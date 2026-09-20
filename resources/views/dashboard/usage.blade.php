@extends('layouts.dashboard')

@section('title', 'Usage & Limits')
@section('heading', 'Usage & Limits')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <p class="text-sm text-slate-600">
            Current entitlement limits for <span class="font-semibold text-slate-900">{{ $tenant->name }}</span>.
            Runtime consumption counters are only shown when the underlying capability exposes a usage contract.
        </p>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3">Capability</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Limit</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Ends</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                @forelse ($limits as $row)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $row['name'] }}</div>
                            <div class="text-xs text-slate-500">{{ $row['key'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ ucfirst($row['type']) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ str_replace('_', ' ', ucfirst($row['status'])) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['limit'] === null ? 'Unlimited / not defined' : $row['limit'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ ucfirst($row['source']) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['ends_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No tenant entitlements are active.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
