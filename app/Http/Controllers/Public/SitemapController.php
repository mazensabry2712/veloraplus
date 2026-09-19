<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SitemapController
{
    public function __construct(
        private readonly SeoManager $seo,
        private readonly TenantResolver $resolver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $urls = [];

        if ($this->seo->isPlatformHost($request->getHost())) {
            $urls[] = $this->seo->platformUrl('/');
        } else {
            $tenant = $this->resolver->resolve($request->getHost());

            if ($tenant === null || $tenant->database_status !== 'ready') {
                abort(404);
            }

            $urls[] = $this->seo->tenantUrl($tenant);
        }

        return response($this->xml($urls), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @param  list<string>  $urls
     */
    private function xml(array $urls): string
    {
        $items = [];

        foreach ($urls as $url) {
            $items[] = '  <url>\n    <loc>'.e($url).'</loc>\n  </url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>\n'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
            .implode("\n", $items)."\n"
            .'</urlset>\n';
    }
}
