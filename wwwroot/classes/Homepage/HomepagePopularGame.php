<?php

declare(strict_types=1);

class HomepagePopularGame extends HomepageTitle
{
    private int $recentPlayers;

    private function __construct(int $id, string $name, string $iconUrl, string $platform, int $recentPlayers)
    {
        parent::__construct($id, $name, $iconUrl, $platform, 'title');
        $this->recentPlayers = $recentPlayers;
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
