<?php

namespace Noros\Core\Support;

use InvalidArgumentException;

final readonly class Seo
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,
        public array $alternates = [],
        public array $jsonLd = [],
        public string $robots = 'index, follow',
        public string $type = 'website',
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public string $twitterCard = 'summary_large_image',
        public ?string $siteName = null,
        public ?string $locale = null,
    ) {
        foreach ([$canonical, $image, ...array_values($alternates)] as $url) {
            if ($url !== null && (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw new InvalidArgumentException('SEO URLs must be absolute HTTP or HTTPS URLs.');
            }
        }
        foreach (array_keys($alternates) as $locale) {
            if (! is_string($locale) || ($locale !== 'x-default' && ! preg_match('/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/', $locale))) {
                throw new InvalidArgumentException('Invalid alternate language code.');
            }
        }
    }

    public function jsonLdHtml(): string
    {
        return json_encode($this->jsonLd, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }

    public function isIndexable(): bool
    {
        return ! preg_match('/(?:^|[\s,:])(?:noindex|none)(?:$|[\s,])/i', $this->robots);
    }
}
