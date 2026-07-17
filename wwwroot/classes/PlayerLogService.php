<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerLogEntry.php';
require_once __DIR__ . '/PlatformSql.php';

class PlayerLogService
{
    public const PAGE_SIZE = 50;

    public function __construct(private readonly PDO $database)
    {
    }

    public function countTrophies(int $accountId, PlayerLogFilter $filter): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM trophy_earned te
            LEFT JOIN trophy t USING (np_communication_id, group_id, order_id)
            LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING (np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            WHERE ttm.status != 2
                AND te.account_id = :account_id
                AND te.earned = 1
                AND (tm.status IS NULL OR tm.status != 1)
        SQL;

        $sql .= $this->buildPlatformClause($filter);

        $query = $this->database->prepare($sql);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @return PlayerLogEntry[]
     */
    public function getTrophies(int $accountId, PlayerLogFilter $filter, int $offset, int $limit = self::PAGE_SIZE): array
    {
        $sql = <<<'SQL'
            SELECT
                te.earned,
                te.progress,
                te.earned_date,
                t.id AS trophy_id,
                t.type AS trophy_type,
                t.name AS trophy_name,
                t.detail AS trophy_detail,
                t.icon_url AS trophy_icon,
                tm.rarity_percent,
                tm.in_game_rarity_percent,
                tm.status AS trophy_status,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tt.id AS game_id,
                tt.name AS game_name,
                ttm.status AS game_status,
                tt.icon_url AS game_icon,
                tt.platform
            FROM trophy_earned te
            LEFT JOIN trophy t USING (np_communication_id, group_id, order_id)
            LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING (np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            WHERE ttm.status != 2
                AND te.account_id = :account_id
                AND te.earned = 1
                AND (tm.status IS NULL OR tm.status != 1)
        SQL;

        $sql .= $this->buildPlatformClause($filter);
        $sql .= $this->buildOrderByClause($filter);
        $sql .= PHP_EOL . '            LIMIT :offset, :limit';

        $query = $this->database->prepare($sql);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        return array_map(
            static fn(array $row): PlayerLogEntry => PlayerLogEntry::fromArray($row),
            $rows
        );
    }

    private function buildPlatformClause(PlayerLogFilter $filter): string
    {
        if (!$filter->hasPlatformFilters()) {
            return '';
        }

        return PlatformSql::buildOrClause($filter->getPlatforms());
    }

    private function buildOrderByClause(PlayerLogFilter $filter): string
    {
        return match ($filter->getSort()) {
            PlayerLogFilter::SORT_RARITY => PHP_EOL . '            ORDER BY tm.rarity_percent, te.earned_date',
            PlayerLogFilter::SORT_IN_GAME_RARITY => PHP_EOL . '            ORDER BY tm.in_game_rarity_percent, te.earned_date',
            default => PHP_EOL . '            ORDER BY te.earned_date DESC',
        };
    }
}
