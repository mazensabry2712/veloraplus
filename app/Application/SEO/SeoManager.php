<?php

namespace App\Application\SEO;

use App\Domain\SEO\SeoMeta;

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
            ogImage: $ogImage,
            locale: $locale ?? config('velora.seo.platform.locale'),
            schema: $schema,
        );
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
