<?php

declare(strict_types=1);

require_once __DIR__ . '/../HistoryIconType.php';

readonly class HomepageTitle extends HomepageItem
{
    protected function __construct(
        final private int $id,
        final private string $name,
        string $iconUrl,
        string $platform,
        HistoryIconType $iconType,
    ) {
        parent::__construct($iconUrl, $platform, $iconType);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSluggedId(Utility $utility): string
    {
        return $this->id . '-' . $utility->slugify($this->name);
    }

    public function getRelativeUrl(Utility $utility): string
    {
        $path = '/game/' . $this->getSluggedId($utility);

        return Uri\Rfc3986\Uri::parse($path)?->toRawString() ?? $path;
    }
}
