<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/GameRecentPlayer.php';
require_once __DIR__ . '/GameRecentPlayersQueryBuilder.php';

class GameRecentPlayersService
{
    public const RECENT_PLAYERS_LIMIT = 10;

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

    public function getGamePlayer(string $npCommunicationId, string $accountId): ?array
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
        // Account IDs are stored as BIGINT UNSIGNED. Bind as string to avoid
        // truncating larger values when PHP integers overflow.
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->execute();

        $gamePlayer = $query->fetch(PDO::FETCH_ASSOC);

        return is_array($gamePlayer) ? $gamePlayer : null;
    }

    /**
     * @return GameRecentPlayer[]
     */
    public function getRecentPlayers(string $npCommunicationId, GamePlayerFilter $filter): array
    {
        $queryBuilder = new GameRecentPlayersQueryBuilder($filter, self::RECENT_PLAYERS_LIMIT);
        $query = $queryBuilder->prepare($this->database, $npCommunicationId);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static fn(array $row): GameRecentPlayer => GameRecentPlayer::fromArray($row),
            $rows
        );
    }

}
