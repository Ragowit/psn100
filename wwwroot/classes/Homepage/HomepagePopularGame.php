<?php

declare(strict_types=1);

require_once __DIR__ . '/../HistoryIconType.php';

final readonly class HomepagePopularGame extends HomepageTitle
{
    private function __construct(
        int $id,
        string $name,
        string $iconUrl,
        string $platform,
        private int $recentPlayers,
    ) {
        parent::__construct($id, $name, $iconUrl, $platform, HistoryIconType::Title);
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            (int) ($row['recent_players'] ?? 0)
        );
    }

    public function getRecentPlayers(): int
    {
        return $this->recentPlayers;
    }
}
