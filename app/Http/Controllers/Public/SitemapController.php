<?php

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Http\Request;

final class SitemapController
{
    public function __invoke(Request $request): Response
    {
        if (! $this->isPlatformHost($request)) {
            abort(404);
        }

        $url = rtrim((string) config('velora.platform.url'), '/').'/';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'\n  <url>'
            .'\n    <loc>'.e($url).'</loc>'
            .'\n  </url>'
            .'\n</urlset>'
            ."\n";

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function isPlatformHost(Request $request): bool
    {
        $platformHost = parse_url((string) config('velora.platform.url'), PHP_URL_HOST)
            ?: config('velora.platform.domain');

        return strtolower((string) $request->getHost()) === strtolower((string) $platformHost);
    }
}
