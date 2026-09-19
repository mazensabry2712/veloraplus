<?php

namespace App\Domain\SEO;

final readonly class SeoMeta
{
    /**
     * @param  array<int, array<string, mixed>>  $schema
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots = 'index,follow',
        public string $ogType = 'website',
        public string $siteName = 'VeloraPlus',
        public ?string $ogImage = null,
        public ?string $locale = null,
        public array $schema = [],
    ) {
    }
}
