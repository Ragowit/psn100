<?php

declare(strict_types=1);

readonly class HomepageTitle extends HomepageItem
{
    protected function __construct(
        private int $id,
        private string $name,
        string $iconUrl,
        string $platform,
        string $iconDirectory,
    ) {
        parent::__construct($iconUrl, $platform, $iconDirectory);
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
        return '/game/' . $this->getSluggedId($utility);
    }
}
