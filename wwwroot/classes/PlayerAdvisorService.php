<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerAdvisableTrophy.php';
require_once __DIR__ . '/Utility.php';

class PlayerAdvisorService
{
    public const PAGE_SIZE = 50;

    private const PLATFORM_FILTERS = [
        'pc' => "tt.platform LIKE '%PC%'",
        'ps3' => "tt.platform LIKE '%PS3%'",
        'ps4' => "tt.platform LIKE '%PS4%'",
        'ps5' => "tt.platform LIKE '%PS5%'",
        'psvita' => "tt.platform LIKE '%PSVITA%'",
        'psvr' => "CONCAT(',', REPLACE(tt.platform, ' ', ''), ',') LIKE '%,PSVR,%'",
        'psvr2' => "tt.platform LIKE '%PSVR2%'",
    ];

    private PDO $database;

    private Utility $utility;

    public function __construct(PDO $database, Utility $utility)
    {
        $this->database = $database;
        $this->utility = $utility;
    }

    public function countAdvisableTrophies(int $accountId, PlayerAdvisorFilter $filter): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING (np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            JOIN trophy_title_player ttp ON
                t.np_communication_id = ttp.np_communication_id
                AND ttp.account_id = :account_id
            LEFT JOIN trophy_earned te_completed ON
                te_completed.np_communication_id = t.np_communication_id
                AND te_completed.order_id = t.order_id
                AND te_completed.account_id = :account_id
                AND te_completed.earned = 1
            WHERE te_completed.account_id IS NULL
                AND ttm.status = 0
                AND tm.status = 0
        SQL;

        $sql .= $this->buildPlatformClause($filter);

        $query = $this->database->prepare($sql);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @return PlayerAdvisableTrophy[]
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
                tm.rarity_percent,
                tm.in_game_rarity_percent,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tt.id AS game_id,
                tt.name AS game_name,
                tt.icon_url AS game_icon,
                tt.platform,
                te_progress.progress
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING (np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            LEFT JOIN trophy_earned te_progress ON
                t.np_communication_id = te_progress.np_communication_id
                AND t.order_id = te_progress.order_id
                AND te_progress.account_id = :account_id
                AND te_progress.earned = 0
            JOIN trophy_title_player ttp ON
                t.np_communication_id = ttp.np_communication_id
                AND ttp.account_id = :account_id
            LEFT JOIN trophy_earned te_completed ON
                te_completed.np_communication_id = t.np_communication_id
                AND te_completed.order_id = t.order_id
                AND te_completed.account_id = :account_id
                AND te_completed.earned = 1
            WHERE te_completed.account_id IS NULL
                AND ttm.status = 0
                AND tm.status = 0
        SQL;

        $sql .= $this->buildPlatformClause($filter);
        $sql .= $this->buildOrderByClause($filter);
        $sql .= ' LIMIT :offset, :limit';

        $query = $this->database->prepare($sql);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $trophies = [];

        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $trophyData) {
            if (!is_array($trophyData)) {
                continue;
            }

            $trophies[] = PlayerAdvisableTrophy::fromArray($trophyData, $this->utility);
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
        if ($filter->getSort() === PlayerAdvisorFilter::SORT_IN_GAME_RARITY) {
            return ' ORDER BY tm.in_game_rarity_percent DESC, ttp.last_updated_date DESC';
        }

        return ' ORDER BY tm.rarity_percent DESC, ttp.last_updated_date DESC';
    }
}
