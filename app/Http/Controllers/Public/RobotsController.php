<?php

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Http\Request;

final class RobotsController
{
    public function __invoke(Request $request): Response
    {
        if (! $this->isPlatformHost($request)) {
            return response("User-agent: *\nDisallow: /\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $sitemapUrl = rtrim((string) config('velora.platform.url'), '/').'/sitemap.xml';

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

    private function isPlatformHost(Request $request): bool
    {
        $platformHost = parse_url((string) config('velora.platform.url'), PHP_URL_HOST)
            ?: config('velora.platform.domain');

        return strtolower((string) $request->getHost()) === strtolower((string) $platformHost);
    }
}
