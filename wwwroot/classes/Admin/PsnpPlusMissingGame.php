<?php

declare(strict_types=1);

final readonly class PsnpPlusMissingGame
{
    public function __construct(final private int $psnprofilesId)
    {
    }

    public function getPsnprofilesId(): int
    {
        return $this->psnprofilesId;
    }

    public function getPsnprofilesUrl(): string
    {
        $path = 'https://psnprofiles.com/trophies/' . $this->psnprofilesId;

        return Uri\Rfc3986\Uri::parse($path)?->toRawString() ?? $path;
    }
}
