<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPagePlayer.php';
require_once __DIR__ . '/AboutPageScanSummary.php';

class AboutPageService
{
    private const DEFAULT_SCAN_LOG_LIMIT = 10;

    private PDO $database;
    private Utility $utility;

    public function __construct(PDO $database, Utility $utility)
    {
        $this->database = $database;
        $this->utility = $utility;
    }

    public function getScanSummary(): AboutPageScanSummary
    {
        $scannedQuery = $this->database->prepare(
            'SELECT COUNT(*) FROM player WHERE last_updated_date >= NOW() - INTERVAL 1 DAY'
        );
        $scannedQuery->execute();

        $newPlayersQuery = $this->database->prepare(
            'SELECT COUNT(*) FROM player WHERE status = 0 AND rank_last_week = 0'
        );
        $newPlayersQuery->execute();

        return new AboutPageScanSummary(
            $this->toInt($scannedQuery->fetchColumn()),
            $this->toInt($newPlayersQuery->fetchColumn())
        );
    }

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

        $players = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $players[] = AboutPagePlayer::fromArray($row, $this->utility);
        }

        return $players;
    }

    private function toInt(mixed $value): int
    {
        if ($value === false || $value === null) {
            return 0;
        }

        return (int) $value;
    }
}
