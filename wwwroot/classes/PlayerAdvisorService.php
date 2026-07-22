<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerAdvisableTrophy.php';
require_once __DIR__ . '/PlayerAdvisorSort.php';
require_once __DIR__ . '/PlatformSql.php';
require_once __DIR__ . '/Utility.php';

class PlayerAdvisorService
{
    public const int PAGE_SIZE = 50;

    public function __construct(
        private readonly PDO $database,
        private readonly Utility $utility,
    ) {
    }

    public function countAdvisableTrophies(int $accountId, PlayerAdvisorFilter $filter): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM trophy_title_player ttp
            JOIN trophy t ON t.np_communication_id = ttp.np_communication_id
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt ON tt.np_communication_id = t.np_communication_id
            JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
            LEFT JOIN trophy_earned te ON
                te.account_id = :account_id
                AND te.np_communication_id = t.np_communication_id
                AND te.order_id = t.order_id
            WHERE ttp.account_id = :account_id
                AND ttm.status = 0
                AND tm.status = 0
                AND (te.account_id IS NULL OR te.earned = 0)
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
                te.progress
            FROM trophy_title_player ttp
            JOIN trophy t ON t.np_communication_id = ttp.np_communication_id
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt ON tt.np_communication_id = t.np_communication_id
            JOIN trophy_title_meta ttm ON ttm.np_communication_id = t.np_communication_id
            LEFT JOIN trophy_earned te ON
                te.account_id = :account_id
                AND te.np_communication_id = t.np_communication_id
                AND te.order_id = t.order_id
            WHERE ttp.account_id = :account_id
                AND ttm.status = 0
                AND tm.status = 0
                AND (te.account_id IS NULL OR te.earned = 0)
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

        return PlatformSql::buildOrClause($filter->getPlatforms());
    }

    private function buildOrderByClause(PlayerAdvisorFilter $filter): string
    {
        return match ($filter->getSort()) {
            PlayerAdvisorSort::InGameRarity => ' ORDER BY tm.in_game_rarity_percent DESC, ttp.last_updated_date DESC',
            PlayerAdvisorSort::Rarity => ' ORDER BY tm.rarity_percent DESC, ttp.last_updated_date DESC',
        };
    }
}
