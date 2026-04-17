<?php

declare(strict_types=1);

interface AuthClientInterface
{
    public function loginWithNpsso(string $npsso): void;

    public function acquireAccessToken(): ?string;

    public function refreshAccessToken(): void;
}
