<?php

use App\Application\SEO\SeoManager;
use App\Http\Middleware\NoIndexRobots;
use Illuminate\Support\Facades\Route;

test('platform home renders centralized SEO metadata', function () {
    config([
        'velora.platform.url' => 'https://velora.com',
        'velora.seo.platform.site_name' => 'VeloraPlus',
        'velora.seo.platform.title' => 'VeloraPlus — SaaS Business Platform',
        'velora.seo.platform.description' => 'A modular SaaS platform.',
        'velora.seo.platform.locale' => 'en_US',
    ]);

    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('<title>VeloraPlus — SaaS Business Platform</title>', false)
        ->assertSee('<meta name="description" content="A modular SaaS platform.">', false)
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertSee('<link rel="canonical" href="https://velora.com/">', false)
        ->assertSee('<meta property="og:url" content="https://velora.com/">', false);
});

test('platform seo manager builds canonical urls from the configured platform url', function () {
    config(['velora.platform.url' => 'https://velora.com/']);

    $seo = app(SeoManager::class)->platform(
        title: 'Features',
        description: 'Platform features.',
        path: '/features',
    );

    expect($seo->canonical)->toBe('https://velora.com/features')
        ->and($seo->robots)->toBe('index,follow');
});

test('platform robots file exposes the platform sitemap and private path exclusions', function () {
    config(['velora.platform.url' => 'https://velora.com']);

    $response = $this->withHeaders(['Host' => 'velora.com'])->get('/robots.txt');

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Allow: /')
        ->assertSee('Disallow: /dashboard')
        ->assertSee('Disallow: /login')
        ->assertSee('Sitemap: https://velora.com/sitemap.xml');
});

test('non-platform robots file denies crawling until tenant public seo is enabled', function () {
    config(['velora.platform.url' => 'https://velora.com']);

    $response = $this->withHeaders(['Host' => 'example.com'])->get('/robots.txt');

    $response->assertSuccessful()
        ->assertSee("User-agent: *
Disallow: /", false);
});

test('platform sitemap contains only the canonical platform home url', function () {
    config(['velora.platform.url' => 'https://velora.com']);

    $response = $this->withHeaders(['Host' => 'velora.com'])->get('/sitemap.xml');

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee('<loc>https://velora.com/</loc>', false)
        ->assertDontSee('tenant_id')
        ->assertDontSee('/dashboard');
});

test('non-platform sitemap is not exposed', function () {
    config(['velora.platform.url' => 'https://velora.com']);

    $this->withHeaders(['Host' => 'example.com'])
        ->get('/sitemap.xml')
        ->assertNotFound();
});

test('noindex middleware sets an explicit indexing response header', function () {
    Route::middleware(NoIndexRobots::class)
        ->get('/__seo-private-test', fn () => response('private'));

    $this->get('/__seo-private-test')
        ->assertSuccessful()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
