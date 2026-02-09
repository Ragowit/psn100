<?php

declare(strict_types=1);

readonly class PsnpPlusMissingGame
{
    public function __construct(private int $psnprofilesId)
    {
    }

    public function getPsnprofilesId(): int
    {
        return $this->psnprofilesId;
    }

    public function getPsnprofilesUrl(): string
    {
        return 'https://psnprofiles.com/trophies/' . $this->psnprofilesId;
    }
}
