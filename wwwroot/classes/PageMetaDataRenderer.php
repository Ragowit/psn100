<?php

declare(strict_types=1);

require_once __DIR__ . '/PageMetaData.php';

class PageMetaDataRenderer
{
    private const OG_SITE_NAME = 'PSN 100%';
    private const TWITTER_CARD = 'summary_large_image';

    public function render(PageMetaData $metaData): string
    {
        if ($metaData->isEmpty()) {
            return '';
        }

        $tags = [];

        $canonicalUrl = $this->escape($metaData->getUrl());
        if ($canonicalUrl !== null) {
            $tags[] = sprintf('<link rel="canonical" href="%s" />', $canonicalUrl);
            $tags[] = sprintf('<meta property="og:url" content="%s">', $canonicalUrl);
        }

        $description = $this->escape($metaData->getDescription());
        if ($description !== null) {
            $tags[] = sprintf('<meta property="og:description" content="%s">', $description);
        }

        $image = $this->escape($metaData->getImage());
        if ($image !== null) {
            $tags[] = sprintf('<meta property="og:image" content="%s">', $image);
        }

        $title = $this->escape($metaData->getTitle());
        if ($title !== null) {
            $tags[] = sprintf('<meta property="og:title" content="%s">', $title);
            $tags[] = sprintf('<meta name="twitter:image:alt" content="%s">', $title);
        }

        $tags[] = sprintf('<meta property="og:site_name" content="%s">', self::OG_SITE_NAME);
        $tags[] = '<meta property="og:type" content="article">';
        $tags[] = sprintf('<meta name="twitter:card" content="%s">', self::TWITTER_CARD);

        return implode(PHP_EOL, $tags);
    }

    private function escape(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
