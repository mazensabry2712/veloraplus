<?php

namespace App\Application\SEO;

use App\Domain\SEO\SeoMeta;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\TenantDomain;

final class SeoManager
{
    public function platformHome(): SeoMeta
    {
        return $this->platform(
            title: config('velora.seo.platform.title', 'VeloraPlus — SaaS Business Platform'),
            description: config('velora.seo.platform.description', 'VeloraPlus is a modular SaaS platform for running business operations.'),
            path: '/',
        );
    }

    public function platform(
        string $title,
        string $description,
        string $path = '/',
        string $robots = 'index,follow',
        string $ogType = 'website',
        ?string $ogImage = null,
        ?string $locale = null,
        array $schema = [],
    ): SeoMeta {
        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $this->platformUrl($path),
            robots: $robots,
            ogType: $ogType,
            siteName: config('velora.seo.platform.site_name', 'VeloraPlus'),
            ogImage: $ogImage,
            locale: $locale ?? config('velora.seo.platform.locale'),
            schema: $schema,
        );
    }

    public function tenantHome(Tenant $tenant): SeoMeta
    {
        $settings = CompanySetting::query()
            ->whereIn('key', [
                'seo.site_title',
                'seo.site_description',
                'seo.default_og_image',
                'seo.robots',
                'seo.locale',
            ])
            ->pluck('value', 'key');

        $title = trim((string) ($settings['seo.site_title'] ?? ''));

        if ($title === '') {
            $title = $tenant->name.' — VeloraPlus';
        }

        $description = trim((string) ($settings['seo.site_description'] ?? ''));

        if ($description === '') {
            $description = 'Public website for '.$tenant->name.' powered by VeloraPlus.';
        }

        $robots = trim((string) ($settings['seo.robots'] ?? 'index,follow'));

        if (! in_array($robots, ['index,follow', 'noindex,nofollow', 'index,nofollow', 'noindex,follow'], true)) {
            $robots = 'index,follow';
        }

        $locale = trim((string) ($settings['seo.locale'] ?? $tenant->locale ?? ''));

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $this->tenantUrl($tenant),
            robots: $robots,
            ogType: 'website',
            siteName: $tenant->name,
            ogImage: $this->absolutePublicUrl((string) ($settings['seo.default_og_image'] ?? '')),
            locale: $locale !== '' ? $locale : null,
            schema: [[
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $tenant->name,
                'url' => $this->tenantUrl($tenant),
            ]],
        );
    }

    public function isPlatformHost(string $host): bool
    {
        $platformHost = parse_url((string) config('velora.platform.url'), PHP_URL_HOST)
            ?: config('velora.platform.domain');

        return strtolower(trim($host)) === strtolower((string) $platformHost);
    }

    public function tenantCanonicalHost(Tenant $tenant): string
    {
        $domain = $tenant->domains()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where(function ($query): void {
                $query->where('type', 'subdomain')
                    ->orWhereNotNull('verified_at');
            })
            ->value('domain');

        if ($domain) {
            return strtolower($domain);
        }

        return strtolower($tenant->slug.'.'.trim((string) config('velora.tenancy.base_domain', 'velora.com'), '.'));
    }

    public function tenantUrl(Tenant $tenant, string $path = '/'): string
    {
        $scheme = rtrim((string) config('velora.tenancy.default_scheme', 'https'), ':/');
        $host = $this->tenantCanonicalHost($tenant);

        if ($path === '/' || $path === '') {
            return $scheme.'://'.$host.'/';
        }

        return $scheme.'://'.$host.'/'.ltrim($path, '/');
    }

    public function absolutePublicUrl(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return null;
    }

    public function platformUrl(string $path = '/'): string
    {
        $baseUrl = rtrim((string) config('velora.platform.url'), '/');

        if ($path === '/' || $path === '') {
            return $baseUrl.'/';
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }
}
