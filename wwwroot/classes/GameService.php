<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameDetails.php';

class GameService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getGame(int $gameId): ?GameDetails
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_title
            WHERE
                id = :id
            SQL
        );
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $game = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($game)) {
            return null;
        }

        return GameDetails::fromArray($game);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public function resolveSort(array $queryParameters): string
    {
        $sort = strtolower((string) ($queryParameters['sort'] ?? 'default'));

        return match ($sort) {
            'date', 'rarity' => $sort,
            default => 'default',
        };
    }

    public function getPlayerAccountId(string $onlineId): ?int
    {
        $onlineId = trim($onlineId);

        if ($onlineId === '') {
            return null;
        }

        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                account_id
            FROM
                player
            WHERE
                online_id = :online_id
            SQL
        );
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        $accountId = $query->fetchColumn();

        if ($accountId === false) {
            return null;
        }

        return (int) $accountId;
    }

    public function getGamePlayer(string $npCommunicationId, int $accountId): ?array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_title_player
            WHERE
                np_communication_id = :np_communication_id
                AND account_id = :account_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $gamePlayer = $query->fetch(PDO::FETCH_ASSOC);

        return is_array($gamePlayer) ? $gamePlayer : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophyGroups(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_group
            WHERE
                np_communication_id = :np_communication_id
            ORDER BY
                (group_id != 'default'),
                group_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $groups = $query->fetchAll(PDO::FETCH_ASSOC);

        return is_array($groups) ? $groups : [];
    }

    public function getTrophyGroupPlayer(string $npCommunicationId, string $groupId, int $accountId): ?array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_group_player
            WHERE
                np_communication_id = :np_communication_id
                AND group_id = :group_id
                AND account_id = :account_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $trophyGroupPlayer = $query->fetch(PDO::FETCH_ASSOC);

        return is_array($trophyGroupPlayer) ? $trophyGroupPlayer : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophies(string $npCommunicationId, string $groupId, ?int $accountId, string $sort): array
    {
        if ($accountId !== null) {
            $sql = <<<'SQL'
                SELECT
                    x.id,
                    x.order_id,
                    x.type,
                    x.name,
                    x.detail,
                    x.icon_url,
                    x.rarity_percent,
                    x.status,
                    x.progress_target_value,
                    x.reward_name,
                    x.reward_image_url,
                    x.earned_date,
                    x.progress,
                    x.earned
                FROM (
                    SELECT
                        t.id,
                        t.order_id,
                        t.type,
                        t.name,
                        t.detail,
                        t.icon_url,
                        t.rarity_percent,
                        t.status,
                        t.progress_target_value,
                        t.reward_name,
                        t.reward_image_url,
                        te.earned_date,
                        te.progress,
                        te.earned
                    FROM
                        trophy t
                    LEFT JOIN (
                        SELECT
                            np_communication_id,
                            group_id,
                            order_id,
                            IFNULL(earned_date, 'No Timestamp') AS earned_date,
                            progress,
                            earned
                        FROM
                            trophy_earned
                        WHERE
                            account_id = :account_id
                    ) AS te USING (np_communication_id, group_id, order_id)
                    WHERE
                        t.np_communication_id = :np_communication_id
                        AND t.group_id = :group_id
                ) AS x
            SQL;

            $sql .= match ($sort) {
                'date' => " ORDER BY x.earned_date IS NULL, x.earned_date, FIELD(x.type, 'bronze', 'silver', 'gold', 'platinum'), x.order_id",
                'rarity' => " ORDER BY x.rarity_percent DESC, FIELD(x.type, 'bronze', 'silver', 'gold', 'platinum'), x.order_id",
                default => " ORDER BY x.order_id",
            };

            $query = $this->database->prepare($sql);
            $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        } else {
            $sql = <<<'SQL'
                SELECT
                    t.id,
                    t.order_id,
                    t.type,
                    t.name,
                    t.detail,
                    t.icon_url,
                    t.rarity_percent,
                    t.status,
                    t.progress_target_value,
                    t.reward_name,
                    t.reward_image_url
                FROM
                    trophy t
                WHERE
                    t.np_communication_id = :np_communication_id
                    AND t.group_id = :group_id
            SQL;

            $sql .= match ($sort) {
                'rarity' => " ORDER BY t.rarity_percent DESC, FIELD(t.type, 'bronze', 'silver', 'gold', 'platinum'), t.order_id",
                default => " ORDER BY t.order_id",
            };

            $query = $this->database->prepare($sql);
        }

        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->execute();

        $trophies = $query->fetchAll(PDO::FETCH_ASSOC);

        return is_array($trophies) ? $trophies : [];
    }
}
