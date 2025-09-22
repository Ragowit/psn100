<?php

declare(strict_types=1);

class HomepageTitle extends HomepageItem
{
    private int $id;

    private string $name;

    protected function __construct(int $id, string $name, string $iconUrl, string $platform, string $iconDirectory)
    {
        parent::__construct($iconUrl, $platform, $iconDirectory);
        $this->id = $id;
        $this->name = $name;
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
