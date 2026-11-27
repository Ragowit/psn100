<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GamePlayerProgress.php';

final readonly class GamePlayerProgressService
{
    public function __construct(final private PDO $database)
    {
    }

    public function getPlayerAccountId(string $onlineId): ?string
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

        return (string) $accountId;
    }

    public function getGamePlayer(string $npCommunicationId, string $accountId): ?GamePlayerProgress
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                np_communication_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress
            FROM
                trophy_title_player
            WHERE
                np_communication_id = :np_communication_id
                AND account_id = :account_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        // Account IDs are stored as BIGINT UNSIGNED. Bind as string to avoid
        // truncating larger values when PHP integers overflow.
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->execute();

        $gamePlayer = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($gamePlayer)) {
            return null;
        }

        return GamePlayerProgress::fromArray($gamePlayer);
    }
}
