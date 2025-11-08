<?php

declare(strict_types=1);

namespace PsnApi;

final class TrophySummary extends AbstractResource
{
    private string $accountId;

    public function __construct(HttpClient $httpClient, string $accountId)
    {
        parent::__construct($httpClient);
        $this->accountId = $accountId;
    }

    public function level(): int
    {
        return (int) ($this->pluck('trophyLevel') ?? 0);
    }

    public function bronze(): int
    {
        return (int) ($this->pluck('earnedTrophies.bronze') ?? 0);
    }

    public function silver(): int
    {
        return (int) ($this->pluck('earnedTrophies.silver') ?? 0);
    }

    public function gold(): int
    {
        return (int) ($this->pluck('earnedTrophies.gold') ?? 0);
    }

    public function platinum(): int
    {
        return (int) ($this->pluck('earnedTrophies.platinum') ?? 0);
    }

    protected function fetch(): object
    {
        return $this->httpClient
            ->get('trophy/v1/users/' . $this->accountId . '/trophySummary')
            ->getJson();
    }
}
