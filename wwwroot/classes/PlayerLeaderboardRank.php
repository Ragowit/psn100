<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLeaderboardRankChange.php';

final readonly class PlayerLeaderboardRank
{
    private const int PAGE_SIZE = 50;

    /**
     * @param array<string, string> $additionalQueryParameters
     */
    private function __construct(
        private string $label,
        private string $basePath,
        private array $additionalQueryParameters,
        private string $onlineId,
        private int $rank,
        private int $previousRank,
        private bool $isActive,
    ) {
    }

    public static function createWorldRank(
        string $basePath,
        string $onlineId,
        int $rank,
        int $previousRank,
        bool $isActive
    ): self {
        return new self(
            'World Rank',
            $basePath,
            [],
            $onlineId,
            max(0, $rank),
            max(0, $previousRank),
            $isActive
        );
    }

    public static function createCountryRank(
        string $basePath,
        string $onlineId,
        string $countryCode,
        int $rank,
        int $previousRank,
        bool $isActive
    ): self {
        return new self(
            'Country Rank',
            $basePath,
            ['country' => $countryCode],
            $onlineId,
            max(0, $rank),
            max(0, $previousRank),
            $isActive
        );
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isAvailable(): bool
    {
        return $this->isActive && $this->rank > 0;
    }

    public function getRank(): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return $this->rank;
    }

    public function getUrl(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $page = max(1, (int) ceil($this->rank / self::PAGE_SIZE));

        $queryParameters = array_merge(
            $this->additionalQueryParameters,
            [
                'page' => (string) $page,
                'player' => $this->onlineId,
            ]
        );

        return $this->basePath . '?' . http_build_query($queryParameters) . '#' . rawurlencode($this->onlineId);
    }

    public function getChange(): ?PlayerLeaderboardRankChange
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return PlayerLeaderboardRankChange::fromRanks($this->rank, $this->previousRank);
    }
}
