<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPageDataProviderInterface.php';
require_once __DIR__ . '/AboutPagePlayer.php';
require_once __DIR__ . '/AboutPageScanSummary.php';

class AboutPageService implements AboutPageDataProviderInterface
{
    private const DEFAULT_SCAN_LOG_LIMIT = 10;

    public function __construct(
        private readonly PDO $database,
        private readonly Utility $utility
    ) {
    }

    #[\Override]
    public function getScanSummary(): AboutPageScanSummary
    {
        $summaryQuery = $this->database->prepare(
            <<<'SQL'
            SELECT
                COALESCE(SUM(last_updated_date >= NOW() - INTERVAL 1 DAY), 0) AS scanned_players,
                COALESCE(SUM(status = 0 AND rank_last_week = 0), 0) AS new_players
            FROM
                player
            SQL
        );
        $summaryQuery->execute();
        $summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

        return new AboutPageScanSummary(
            $this->toInt($summary['scanned_players'] ?? 0),
            $this->toInt($summary['new_players'] ?? 0)
        );
    }

    /**
     * @return list<AboutPagePlayer>
     */
    #[\Override]
    public function getScanLogPlayers(int $limit = self::DEFAULT_SCAN_LOG_LIMIT): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                p.online_id,
                p.country,
                p.avatar_url,
                p.last_updated_date,
                p.level,
                p.progress,
                p.rank_last_week,
                p.status,
                p.trophy_count_npwr,
                p.trophy_count_sony,
                r.ranking
            FROM
                player p
                LEFT JOIN player_ranking r ON p.account_id = r.account_id
            WHERE
                p.status = 0
            ORDER BY
                p.last_updated_date DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row): AboutPagePlayer => AboutPagePlayer::fromArray($row, $this->utility),
            $rows
        );
    }

    private function toInt(mixed $value): int
    {
        if ($value === false || $value === null) {
            return 0;
        }

        return (int) $value;
    }
}
