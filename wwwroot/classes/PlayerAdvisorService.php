<?php

declare(strict_types=1);

class PlayerAdvisorService
{
    public const PAGE_SIZE = 50;

    private const PLATFORM_FILTERS = [
        'pc' => "tt.platform LIKE '%PC%'",
        'ps3' => "tt.platform LIKE '%PS3%'",
        'ps4' => "tt.platform LIKE '%PS4%'",
        'ps5' => "tt.platform LIKE '%PS5%'",
        'psvita' => "tt.platform LIKE '%PSVITA%'",
        'psvr' => "(tt.platform LIKE '%PSVR' OR tt.platform LIKE '%PSVR,%')",
        'psvr2' => "tt.platform LIKE '%PSVR2%'",
    ];

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function countAdvisableTrophies(int $accountId, PlayerAdvisorFilter $filter): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM trophy t
            JOIN trophy_title tt USING (np_communication_id)
            LEFT JOIN trophy_earned te ON
                t.np_communication_id = te.np_communication_id
                AND t.order_id = te.order_id
                AND te.account_id = :account_id
            JOIN trophy_title_player ttp ON
                t.np_communication_id = ttp.np_communication_id
                AND ttp.account_id = :account_id
            WHERE (te.earned IS NULL OR te.earned = 0)
                AND tt.status = 0
                AND t.status = 0
        SQL;

        $sql .= $this->buildPlatformClause($filter);

        $query = $this->database->prepare($sql);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAdvisableTrophies(int $accountId, PlayerAdvisorFilter $filter, int $offset, int $limit = self::PAGE_SIZE): array
    {
        $sql = <<<'SQL'
            SELECT
                t.id AS trophy_id,
                t.type AS trophy_type,
                t.name AS trophy_name,
                t.detail AS trophy_detail,
                t.icon_url AS trophy_icon,
                t.rarity_percent,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tt.id AS game_id,
                tt.name AS game_name,
                tt.icon_url AS game_icon,
                tt.platform,
                te.progress
            FROM trophy t
            JOIN trophy_title tt USING (np_communication_id)
            LEFT JOIN trophy_earned te ON
                t.np_communication_id = te.np_communication_id
                AND t.order_id = te.order_id
                AND te.account_id = :account_id
            JOIN trophy_title_player ttp ON
                t.np_communication_id = ttp.np_communication_id
                AND ttp.account_id = :account_id
            WHERE (te.earned IS NULL OR te.earned = 0)
                AND tt.status = 0
                AND t.status = 0
        SQL;

        $sql .= $this->buildPlatformClause($filter);
        $sql .= $this->buildOrderByClause($filter);
        $sql .= ' LIMIT :offset, :limit';

        $query = $this->database->prepare($sql);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $trophies = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($trophies)) {
            return [];
        }

        return $trophies;
    }

    private function buildPlatformClause(PlayerAdvisorFilter $filter): string
    {
        if (!$filter->hasPlatformFilters()) {
            return '';
        }

        $clauses = [];

        foreach ($filter->getPlatforms() as $platform) {
            if (isset(self::PLATFORM_FILTERS[$platform])) {
                $clauses[] = self::PLATFORM_FILTERS[$platform];
            }
        }

        if ($clauses === []) {
            return '';
        }

        return ' AND (' . implode(' OR ', $clauses) . ')';
    }

    private function buildOrderByClause(PlayerAdvisorFilter $filter): string
    {
        if ($filter->isSort(PlayerAdvisorFilter::SORT_RARITY)) {
            return ' ORDER BY t.rarity_percent DESC, ttp.last_updated_date DESC';
        }

        return ' ORDER BY ttp.last_updated_date DESC, t.rarity_percent DESC';
    }
}
