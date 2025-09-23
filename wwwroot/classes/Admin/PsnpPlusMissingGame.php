<?php

declare(strict_types=1);

class PsnpPlusMissingGame
{
    private int $psnprofilesId;

    public function __construct(int $psnprofilesId)
    {
        $this->psnprofilesId = $psnprofilesId;
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
