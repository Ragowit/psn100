<?php

declare(strict_types=1);

class TrophyService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getTrophyById(int $trophyId): ?array
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
                t.rarity_percent,
                t.status,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tt.id AS game_id,
                tt.name AS game_name,
                tt.icon_url AS game_icon,
                tt.platform
            FROM
                trophy t
            JOIN trophy_title tt USING(np_communication_id)
            WHERE
                t.id = :id'
        );

        $query->bindValue(':id', $trophyId, PDO::PARAM_INT);
        $query->execute();

        $trophy = $query->fetch(PDO::FETCH_ASSOC);

        return $trophy === false ? null : $trophy;
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
    ): ?array {
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

        if ((int) $playerTrophy['earned'] === 1 && $progressTargetValue !== null) {
            $playerTrophy['progress'] = $progressTargetValue;
        }

        return $playerTrophy;
    }
}
