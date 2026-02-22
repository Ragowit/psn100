<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/Game/GamePlayerProgress.php';
require_once __DIR__ . '/Game/GameTrophyGroupPlayer.php';

class GameService
{
    private const TROPHY_TYPE_ORDER_SQL = "FIELD(%s, 'bronze', 'silver', 'gold', 'platinum')";

    public function __construct(private readonly PDO $database) {}

    public function getGame(int $gameId): ?GameDetails
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                tt.id,
                tt.np_communication_id,
                tt.name,
                tt.detail,
                tt.icon_url,
                tt.platform,
                tt.bronze,
                tt.silver,
                tt.gold,
                tt.platinum,
                tt.set_version,
                ttm.message,
                ttm.status,
                ttm.recent_players,
                ttm.owners_completed,
                ttm.owners,
                ttm.difficulty,
                ttm.psnprofiles_id,
                ttm.parent_np_communication_id,
                ttm.region,
                ttm.rarity_points,
                ttm.in_game_rarity_points,
                ttm.obsolete_ids
            FROM
                trophy_title tt
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                tt.id = :id
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

    public function getGamePlayer(string $npCommunicationId, int $accountId): ?GamePlayerProgress
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

        if (!is_array($gamePlayer)) {
            return null;
        }

        return GamePlayerProgress::fromArray($gamePlayer);
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

    public function getTrophyGroupPlayer(string $npCommunicationId, string $groupId, int $accountId): ?GameTrophyGroupPlayer
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

        if (!is_array($trophyGroupPlayer)) {
            return null;
        }

        return GameTrophyGroupPlayer::fromArray($trophyGroupPlayer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophies(string $npCommunicationId, string $groupId, ?int $accountId, string $sort): array
    {
        if ($accountId !== null) {
            $sql = <<<'SQL'
                WITH account_trophy_earned AS (
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
                )
                SELECT
                    t.id,
                    t.order_id,
                    t.type,
                    t.name,
                    t.detail,
                    t.icon_url,
                    tm.rarity_percent,
                    tm.in_game_rarity_percent,
                    tm.status,
                    t.progress_target_value,
                    t.reward_name,
                    t.reward_image_url,
                    te.earned_date,
                    te.progress,
                    te.earned
                FROM
                    trophy t
                JOIN trophy_meta tm ON tm.trophy_id = t.id
                LEFT JOIN account_trophy_earned te USING (np_communication_id, group_id, order_id)
                WHERE
                    t.np_communication_id = :np_communication_id
                    AND t.group_id = :group_id
            SQL;

            $sql .= $this->buildAccountSortSql($sort);

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
                    tm.rarity_percent,
                    tm.in_game_rarity_percent,
                    tm.status,
                    t.progress_target_value,
                    t.reward_name,
                    t.reward_image_url
                FROM
                    trophy t
                JOIN trophy_meta tm ON tm.trophy_id = t.id
                WHERE
                    t.np_communication_id = :np_communication_id
                    AND t.group_id = :group_id
            SQL;

            $sql .= $this->buildPublicSortSql($sort);

            $query = $this->database->prepare($sql);
        }

        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->execute();

        $trophies = $query->fetchAll(PDO::FETCH_ASSOC);

        return is_array($trophies) ? $trophies : [];
    }

    private function buildAccountSortSql(string $sort): string
    {
        $trophyTypeSort = sprintf(self::TROPHY_TYPE_ORDER_SQL, 't.type');

        return match ($sort) {
            'date' => " ORDER BY te.earned_date IS NULL, te.earned_date, {$trophyTypeSort}, t.order_id",
            'rarity' => " ORDER BY tm.rarity_percent DESC, {$trophyTypeSort}, t.order_id",
            default => ' ORDER BY t.order_id',
        };
    }

    private function buildPublicSortSql(string $sort): string
    {
        if ($sort !== 'rarity') {
            return ' ORDER BY t.order_id';
        }

        $trophyTypeSort = sprintf(self::TROPHY_TYPE_ORDER_SQL, 't.type');

        return " ORDER BY tm.rarity_percent DESC, {$trophyTypeSort}, t.order_id";
    }
}
