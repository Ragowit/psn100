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
        final private string $label,
        final private string $basePath,
        final private array $additionalQueryParameters,
        final private string $onlineId,
        final private int $rank,
        final private int $previousRank,
        final private bool $isActive,
    ) {
    }

    #[\NoDiscard]
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

    #[\NoDiscard]
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

        $query = http_build_query(
            [
                ...$this->additionalQueryParameters,
                'page' => (string) $page,
                'player' => $this->onlineId,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return Uri\Rfc3986\Uri::parse($this->basePath)
            ?->withQuery($query)
            ->withFragment(rawurlencode($this->onlineId))
            ->toRawString()
            ?? $this->basePath . '?' . $query . '#' . rawurlencode($this->onlineId);
    }

    public function getChange(): ?PlayerLeaderboardRankChange
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return PlayerLeaderboardRankChange::fromRanks($this->rank, $this->previousRank);
    }
}
