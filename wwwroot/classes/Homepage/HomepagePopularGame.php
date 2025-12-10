<?php

declare(strict_types=1);

readonly class HomepagePopularGame extends HomepageTitle
{
    private function __construct(
        int $id,
        string $name,
        string $iconUrl,
        string $platform,
        private int $recentPlayers,
    ) {
        parent::__construct($id, $name, $iconUrl, $platform, 'title');
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : 0,
            (string) ($row['name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            isset($row['recent_players']) ? (int) $row['recent_players'] : 0
        );
    }

    public function getRecentPlayers(): int
    {
        return $this->recentPlayers;
    }
}
