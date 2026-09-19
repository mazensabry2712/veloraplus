<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SitemapController
{
    public function __construct(
        private readonly SeoManager $seo,
    ) {
    }

    public function __invoke(Request $request, TenantContext $context): Response
    {
        $urls = [];

        if ($this->seo->isPlatformHost($request->getHost())) {
            $urls[] = $this->seo->platformUrl('/');
        } else {
            if (! $context->check()) {
                abort(404);
            }

            $tenant = $context->current();

            $urls[] = $this->seo->tenantUrl($tenant);
            $urls[] = $this->seo->tenantUrl($tenant, '/services');

            $services = Service::query()
                ->where('status', 'active')
                ->where('online_bookable', true)
                ->orderBy('slug')
                ->pluck('slug');

            foreach ($services as $slug) {
                $urls[] = $this->seo->tenantUrl($tenant, '/services/'.$slug);
            }
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
