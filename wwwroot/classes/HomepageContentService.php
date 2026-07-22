<?php

declare(strict_types=1);

require_once __DIR__ . '/Homepage/HomepageItem.php';
require_once __DIR__ . '/Homepage/HomepageTitle.php';
require_once __DIR__ . '/Homepage/HomepageNewGame.php';
require_once __DIR__ . '/Homepage/HomepageDlc.php';
require_once __DIR__ . '/Homepage/HomepagePopularGame.php';
require_once __DIR__ . '/HomepagePopularGamesFilter.php';
require_once __DIR__ . '/GameAvailabilityStatus.php';
require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/PlatformSql.php';

class HomepageContentService
{
    private const int DEFAULT_NEW_GAME_LIMIT = 8;
    private const int DEFAULT_NEW_DLCS_LIMIT = 8;
    private const int DEFAULT_POPULAR_GAME_LIMIT = 10;

    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return HomepageNewGame[]
     */
    public function getNewGames(int $limit = self::DEFAULT_NEW_GAME_LIMIT): array
    {
        $mergedStatus = GameAvailabilityStatus::MERGED->value;

        $query = $this->database->prepare(
            <<<SQL
            SELECT
                tt.id,
                tt.name,
                tt.icon_url,
                tt.platform,
                tt.platinum,
                tt.gold,
                tt.silver,
                tt.bronze
            FROM
                trophy_title tt
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                ttm.status <> {$mergedStatus}
            ORDER BY
                tt.id DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        return array_map(HomepageNewGame::fromArray(...), $rows);
    }

    /**
     * @return HomepageDlc[]
     */
    public function getNewDlcs(int $limit = self::DEFAULT_NEW_DLCS_LIMIT): array
    {
        $mergedStatus = GameAvailabilityStatus::MERGED->value;

        $query = $this->database->prepare(
            <<<SQL
            SELECT
                tt.id,
                tt.name AS game_name,
                tt.platform,
                tg.icon_url,
                tg.name AS group_name,
                tg.group_id,
                tg.bronze,
                tg.silver,
                tg.gold
            FROM
                trophy_group tg
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_title_meta ttm USING (np_communication_id)
            WHERE
                ttm.status <> {$mergedStatus}
                AND tg.group_id <> 'default'
            ORDER BY
                tg.id DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        return array_map(HomepageDlc::fromArray(...), $rows);
    }

    /**
     * @return HomepagePopularGame[]
     */
    public function getPopularGames(
        int $limit = self::DEFAULT_POPULAR_GAME_LIMIT,
        ?HomepagePopularGamesFilter $filter = null
    ): array {
        $filter ??= HomepagePopularGamesFilter::fromArray([]);
        $whereClause = implode(' AND ', $this->buildPopularGamesWhereClauses($filter));

        $query = $this->database->prepare(
            <<<SQL
            SELECT
                tt.id,
                tt.icon_url,
                tt.platform,
                tt.name,
                ttm.recent_players
            FROM
                trophy_title tt
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                {$whereClause}
            ORDER BY
                ttm.recent_players DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        return array_map(HomepagePopularGame::fromArray(...), $rows);
    }

    /**
     * @return list<string>
     */
    private function buildPopularGamesWhereClauses(HomepagePopularGamesFilter $filter): array
    {
        $mergedStatus = GameAvailabilityStatus::MERGED->value;
        $conditions = ["ttm.status <> {$mergedStatus}"];

        if ($filter->isExclusiveOnly() && $filter->hasPlatformFilter()) {
            $conditions[] = 'tt.platform = ' . $this->database->quote($filter->getPlatformDatabaseValue());
        } elseif ($filter->isExclusiveOnly()) {
            $conditions[] = "tt.platform NOT LIKE '%,%'";
        } elseif ($filter->hasPlatformFilter()) {
            $platform = Platform::tryFrom($filter->getPlatform());
            if ($platform !== null) {
                $conditions[] = PlatformSql::conditionFor($platform);
            }
        }

        return $conditions;
    }
}
