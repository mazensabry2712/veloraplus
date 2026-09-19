<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RobotsController
{
    public function __construct(
        private readonly SeoManager $seo,
        private readonly TenantResolver $resolver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ($this->seo->isPlatformHost($request->getHost())) {
            return $this->platformResponse();
        }

        $tenant = $this->resolver->resolve($request->getHost());

        if ($tenant === null || $tenant->database_status !== 'ready') {
            return $this->denyResponse();
        }

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /account',
            'Disallow: /billing',
            'Disallow: /settings',
            'Disallow: /login',
            'Disallow: /register',
            'Sitemap: '.$this->seo->tenantUrl($tenant, '/sitemap.xml'),
            '',
        ]);

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function platformResponse(): Response
    {
        $sitemapUrl = $this->seo->platformUrl('/sitemap.xml');

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /account',
            'Disallow: /billing',
            'Disallow: /settings',
            'Disallow: /login',
            'Disallow: /register',
            'Sitemap: '.$sitemapUrl,
            '',
        ]);

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function denyResponse(): Response
    {
        return response("User-agent: *\nDisallow: /\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
