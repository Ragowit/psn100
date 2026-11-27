<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/GamePlayerProgressService.php';
require_once __DIR__ . '/GameRecentPlayer.php';
require_once __DIR__ . '/GameRecentPlayersQueryBuilder.php';

final readonly class GameRecentPlayersService
{
    public const int RECENT_PLAYERS_LIMIT = 10;

    public function __construct(final private PDO $database)
    {
    }

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

    public function getPlayerAccountId(string $onlineId): ?string
    {
        return (new GamePlayerProgressService($this->database))->getPlayerAccountId($onlineId);
    }

    public function getGamePlayer(string $npCommunicationId, string $accountId): ?GamePlayerProgress
    {
        return (new GamePlayerProgressService($this->database))->getGamePlayer($npCommunicationId, $accountId);
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

        return array_map(GameRecentPlayer::fromArray(...), $rows);
    }

}
