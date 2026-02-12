<?php

declare(strict_types=1);

require_once __DIR__ . '/GameListItem.php';
require_once __DIR__ . '/SearchQueryHelper.php';

class GameListService
{
    private const PAGE_LIMIT = 40;

    public function __construct(
        private readonly PDO $database,
        private readonly SearchQueryHelper $searchQueryHelper
    ) {
    }

    public function resolvePlayer(?string $onlineId): ?string
    {
        if ($onlineId === null) {
            return null;
        }

        $onlineId = trim($onlineId);
        if ($onlineId === '') {
            return null;
        }

        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                `status`
            FROM
                player
            WHERE
                online_id = :online_id
            SQL
        );
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        $status = $query->fetchColumn();
        if ($status === false) {
            return $onlineId;
        }

        $status = (int) $status;
        if ($status === 1 || $status === 3) {
            return null;
        }

        return $onlineId;
    }

    public function getLimit(): int
    {
        return self::PAGE_LIMIT;
    }

    public function getOffset(GameListFilter $filter): int
    {
        return $filter->getOffset(self::PAGE_LIMIT);
    }

    public function countGames(GameListFilter $filter): int
    {
        $sql = $this->buildCountQuery($filter);
        $statement = $this->database->prepare($sql);
        $this->bindCommonParameters($statement, $filter, false);
        $statement->execute();

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @return GameListItem[]
     */
    public function getGames(GameListFilter $filter): array
    {
        $sql = $this->buildListQuery($filter);
        $statement = $this->database->prepare($sql);
        $this->bindCommonParameters($statement, $filter, true);
        $statement->bindValue(':offset', $this->getOffset($filter), PDO::PARAM_INT);
        $statement->bindValue(':limit', self::PAGE_LIMIT, PDO::PARAM_INT);
        $statement->execute();

        $games = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn(array $row): GameListItem => GameListItem::fromArray($row),
            $games
        );
    }

    private function bindCommonParameters(PDOStatement $statement, GameListFilter $filter, bool $bindPrefix): void
    {
        $player = $filter->getPlayer() ?? '';
        $statement->bindValue(':online_id', $player, PDO::PARAM_STR);

        if ($filter->shouldApplySearch()) {
            $this->searchQueryHelper->bindSearchParameters(
                $statement,
                $filter->getSearch(),
                $bindPrefix
            );
        }
    }

    private function buildCountQuery(GameListFilter $filter): string
    {
        $conditions = $this->buildConditions($filter, true);

        return sprintf(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                trophy_title tt
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            LEFT JOIN player p ON p.online_id = :online_id
            LEFT JOIN trophy_title_player ttp ON
                ttp.np_communication_id = tt.np_communication_id
                AND ttp.progress = 100
                AND ttp.account_id = p.account_id
            WHERE
                %s
            SQL,
            $conditions
        );
    }

    private function buildListQuery(GameListFilter $filter): string
    {
        $columns = [
            'tt.np_communication_id',
            'tt.id',
            'tt.name',
            'ttm.status AS status',
            'tt.icon_url',
            'tt.platform',
            'ttm.owners AS owners',
            'ttm.difficulty AS difficulty',
            'tt.platinum',
            'tt.gold',
            'tt.silver',
            'tt.bronze',
            'ttm.rarity_points AS rarity_points',
            'ttm.in_game_rarity_points AS in_game_rarity_points',
            'ttp.progress',
        ];

        $columns = $this->searchQueryHelper->addFulltextSelectColumns(
            $columns,
            'tt.name',
            $filter->shouldApplySearch(),
            $filter->getSearch()
        );

        $conditions = $this->buildConditions($filter, false);
        $orderBy = $this->buildOrderByClause($filter);

        return sprintf(
            <<<'SQL'
            SELECT
                %s
            FROM
                trophy_title tt
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            LEFT JOIN player p ON p.online_id = :online_id
            LEFT JOIN trophy_title_player ttp ON
                ttp.np_communication_id = tt.np_communication_id
                AND ttp.progress = 100
                AND ttp.account_id = p.account_id
            WHERE
                %s
            %s
            LIMIT
                :offset, :limit
            SQL,
            implode(', ', $columns),
            $conditions,
            $orderBy
        );
    }

    private function buildConditions(GameListFilter $filter, bool $forCount): string
    {
        $conditions = [
            match ($filter->getSort()) {
                GameListFilter::SORT_COMPLETION => "ttm.status = 0 AND (tt.bronze + tt.silver + tt.gold + tt.platinum) != 0",
                GameListFilter::SORT_RARITY, GameListFilter::SORT_IN_GAME_RARITY => 'ttm.status = 0',
                default => 'ttm.status != 2',
            },
        ];

        $conditions = $this->searchQueryHelper->appendFulltextCondition(
            $conditions,
            $filter->shouldApplySearch(),
            'tt.name',
            $filter->getSearch()
        );

        if ($filter->shouldFilterUncompleted()) {
            $conditions[] = 'ttp.progress IS NULL';
        }

        $platformCondition = $this->buildPlatformCondition($filter);
        if ($platformCondition !== null) {
            $conditions[] = $platformCondition;
        }

        return implode(' AND ', $conditions);
    }

    private function buildOrderByClause(GameListFilter $filter): string
    {
        return match ($filter->getSort()) {
            GameListFilter::SORT_COMPLETION => 'ORDER BY difficulty DESC, owners DESC, `name`',
            GameListFilter::SORT_OWNERS => 'ORDER BY owners DESC, `name`',
            GameListFilter::SORT_RARITY => 'ORDER BY rarity_points DESC, owners DESC, `name`',
            GameListFilter::SORT_IN_GAME_RARITY => 'ORDER BY in_game_rarity_points DESC, owners DESC, `name`',
            GameListFilter::SORT_SEARCH => 'ORDER BY exact_match DESC, prefix_match DESC, score DESC, `name`, tt.id',
            default => 'ORDER BY id DESC',
        };
    }

    private function buildPlatformCondition(GameListFilter $filter): ?string
    {
        if (!$filter->hasPlatformFilters()) {
            return null;
        }

        $conditions = array_map(
            static function (string $platform): string {
                if ($platform === GameListFilter::PLATFORM_PSVR) {
                    return "REGEXP_LIKE(tt.platform, '(^|,)PSVR(,|$)')";
                }

                return sprintf("tt.platform LIKE '%%%s%%'", strtoupper($platform));
            },
            $filter->getSelectedPlatforms()
        );

        if ($conditions === []) {
            return null;
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }
}
