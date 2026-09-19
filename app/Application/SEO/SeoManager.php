<?php

namespace App\Application\SEO;

use App\Domain\SEO\SeoMeta;
use App\Models\CompanySetting;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Support\Str;

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

    public function tenantServices(Tenant $tenant): SeoMeta
    {
        $url = $this->tenantUrl($tenant, '/services');

        return new SeoMeta(
            title: 'Services | '.$tenant->name,
            description: 'Explore services available from '.$tenant->name.'.',
            canonical: $url,
            robots: 'index,follow',
            ogType: 'website',
            siteName: $tenant->name,
            locale: $tenant->locale ?: null,
            schema: [[
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Services at '.$tenant->name,
                'url' => $url,
            ]],
        );
    }

    public function tenantService(Tenant $tenant, Service $service): SeoMeta
    {
        $url = $this->tenantUrl($tenant, '/services/'.$service->slug);
        $title = trim((string) $service->seo_title);

        if ($title === '') {
            $title = $service->name.' | '.$tenant->name;
        }

        $description = trim((string) $service->seo_description);

        if ($description === '') {
            $description = trim((string) $service->description);

            if ($description === '') {
                $description = 'Learn about '.$service->name.' at '.$tenant->name.'.';
            }
        }

        $schemas = [[
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service->name,
            'url' => $url,
            'provider' => [
                '@type' => 'Organization',
                'name' => $tenant->name,
                'url' => $this->tenantUrl($tenant),
            ],
        ]];

        if ($service->description) {
            $schemas[0]['description'] = $service->description;
        }

        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $tenant->name,
                    'item' => $this->tenantUrl($tenant),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Services',
                    'item' => $this->tenantUrl($tenant, '/services'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $service->name,
                    'item' => $url,
                ],
            ],
        ];

        return new SeoMeta(
            title: $title,
            description: Str::limit($description, 320, ''),
            canonical: $url,
            robots: 'index,follow',
            ogType: 'website',
            siteName: $tenant->name,
            ogImage: $this->absolutePublicUrl((string) $service->social_image_url),
            locale: $tenant->locale ?: null,
            schema: $schemas,
        );
    }

    public function tenantBooking(Tenant $tenant, Service $service): SeoMeta
    {
        $serviceUrl = $this->tenantUrl($tenant, '/services/'.$service->slug);

        return new SeoMeta(
            title: 'Book '.$service->name.' | '.$tenant->name,
            description: 'Booking page for '.$service->name.' at '.$tenant->name.'.',
            canonical: $serviceUrl,
            robots: 'noindex,nofollow',
            ogType: 'website',
            siteName: $tenant->name,
            locale: $tenant->locale ?: null,
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
