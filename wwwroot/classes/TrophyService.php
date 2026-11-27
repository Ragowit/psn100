<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyDetails.php';
require_once __DIR__ . '/PlayerTrophyProgress.php';
require_once __DIR__ . '/TrophyAchiever.php';

final readonly class TrophyService
{
    private const int FIRST_ACHIEVER_LIMIT = 50;

    public function __construct(final private PDO $database)
    {
    }

    public function getTrophyById(int $trophyId): ?TrophyDetails
    {
        $query = $this->database->prepare(
            'SELECT
                t.id AS trophy_id,
                t.np_communication_id,
                t.group_id,
                t.order_id,
                t.type AS trophy_type,
                t.name AS trophy_name,
                t.detail AS trophy_detail,
                t.icon_url AS trophy_icon,
                tm.rarity_percent,
                tm.in_game_rarity_percent,
                tm.status,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tt.id AS game_id,
                tt.name AS game_name,
                tt.icon_url AS game_icon,
                tt.platform
            FROM
                trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING(np_communication_id)
            WHERE
                t.id = :id'
        );

        $query->bindValue(':id', $trophyId, PDO::PARAM_INT);
        $query->execute();

        $trophy = $query->fetch(PDO::FETCH_ASSOC);

        if ($trophy === false) {
            return null;
        }

        return TrophyDetails::fromArray($trophy);
    }

    public function getPlayerAccountId(string $onlineId): ?int
    {
        $query = $this->database->prepare(
            'SELECT account_id FROM player WHERE online_id = :online_id'
        );

        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        $accountId = $query->fetchColumn();

        if ($accountId === false) {
            return null;
        }

        return (int) $accountId;
    }

    public function getPlayerTrophy(
        int $accountId,
        string $npCommunicationId,
        int $orderId,
        ?string $progressTargetValue
    ): ?PlayerTrophyProgress {
        $query = $this->database->prepare(
            'SELECT
                earned_date,
                progress,
                earned
            FROM
                trophy_earned
            WHERE
                np_communication_id = :np_communication_id AND
                order_id = :order_id AND
                account_id = :account_id'
        );

        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $playerTrophy = $query->fetch(PDO::FETCH_ASSOC);

        if ($playerTrophy === false) {
            return null;
        }

        return PlayerTrophyProgress::fromArray($playerTrophy, $progressTargetValue);
    }

    /**
     * @return list<TrophyAchiever>
     */
    public function getFirstAchievers(string $npCommunicationId, int $orderId): array
    {
        $achievers = $this->fetchFirstAchieverRows($npCommunicationId, $orderId, true, self::FIRST_ACHIEVER_LIMIT);
        $remaining = self::FIRST_ACHIEVER_LIMIT - count($achievers);

        if ($remaining > 0) {
            // Separate reads let the ranking swap proceed between queries. Exclude players
            // already selected in case a trophy timestamp changes before the fallback.
            $achievers = array_merge($achievers, $this->fetchFirstAchieverRows(
                $npCommunicationId,
                $orderId,
                false,
                $remaining,
                array_column($achievers, 'account_id')
            ));
        }

        return array_map(TrophyAchiever::fromArray(...), $achievers);
    }

    /**
     * @param list<int|string> $excludedAccountIds
     * @return list<array<string, mixed>>
     */
    private function fetchFirstAchieverRows(
        string $npCommunicationId,
        int $orderId,
        bool $withTimestamp,
        int $limit,
        array $excludedAccountIds = []
    ): array {
        $mysql = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $indexHint = $mysql ? 'FORCE INDEX (idx_te_npcomm_order_earned_date)' : '';
        $join = $mysql ? 'STRAIGHT_JOIN' : 'JOIN';
        $dateFilter = $withTimestamp ? 'IS NOT NULL' : 'IS NULL';
        $orderClause = $withTimestamp ? 'ORDER BY te.earned_date ASC' : '';

        $accountBindings = [];
        $accountExpressions = [];
        foreach ($excludedAccountIds as $index => $accountId) {
            $parameter = ':excluded_account_' . $index;
            $accountBindings[$parameter] = (string) $accountId;
            // Account IDs can exceed PHP_INT_MAX. Compare values using exact arithmetic.
            $accountExpressions[] = "CAST($parameter AS DECIMAL(20, 0))";
        }
        $accountFilter = $accountExpressions === []
            ? ''
            : 'AND te.account_id NOT IN (' . implode(', ', $accountExpressions) . ')';

        $query = $this->database->prepare(
            <<<SQL
            SELECT
                te.account_id,
                p.avatar_url,
                p.online_id,
                p.trophy_count_npwr,
                p.trophy_count_sony,
                IFNULL(te.earned_date, 'No Timestamp') AS earned_date
            FROM trophy_earned te $indexHint
            $join player_ranking r ON r.account_id = te.account_id
            $join player p ON p.account_id = te.account_id
            WHERE te.np_communication_id = :np_communication_id
                AND te.order_id = :order_id
                AND te.earned = 1
                AND te.earned_date $dateFilter
                AND r.ranking <= 10000
                $accountFilter
            $orderClause
            LIMIT :limit
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($accountBindings as $parameter => $accountId) {
            $query->bindValue($parameter, $accountId, PDO::PARAM_STR);
        }
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<TrophyAchiever>
     */
    public function getLatestAchievers(string $npCommunicationId, int $orderId): array
    {
        // Drive from player_ranking so trophy_earned is probed by account_id
        // (HASH partition key) instead of scanning all partitions by title.
        $query = $this->database->prepare(<<<'SQL'
            SELECT
                p.avatar_url,
                p.online_id,
                p.trophy_count_npwr,
                p.trophy_count_sony,
                IFNULL(te.earned_date, 'No Timestamp') AS earned_date
            FROM
                player_ranking r
                JOIN trophy_earned te
                    ON te.account_id = r.account_id
                    AND te.np_communication_id = :np_communication_id
                    AND te.order_id = :order_id
                    AND te.earned = 1
                JOIN player p ON r.account_id = p.account_id
            WHERE
                r.ranking <= 10000
            ORDER BY te.earned_date DESC
            LIMIT 50
        SQL);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $query->execute();

        /** @var array<int, array<string, mixed>> $achievers */
        $achievers = $query->fetchAll(PDO::FETCH_ASSOC);

        return array_map(TrophyAchiever::fromArray(...), $achievers);
    }
}
