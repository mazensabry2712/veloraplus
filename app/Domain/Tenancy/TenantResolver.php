<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;

final class TenantResolver
{
    public function resolve(string $host): ?Tenant
    {
        $domain = $this->normalize($host);

        if ($domain === '') {
            return null;
        }

        return TenantDomain::query()
            ->where('domain', $domain)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('type', 'subdomain')
                    ->orWhereNotNull('verified_at');
            })
            ->whereHas('tenant', function ($query): void {
                $query->whereIn('status', ['active', 'trial']);
            })
            ->with('tenant')
            ->first()?->tenant;
    }

    public function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
        return rtrim($host, '.');
    }
}