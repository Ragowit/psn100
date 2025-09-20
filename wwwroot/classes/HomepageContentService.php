<?php

class HomepageContentService
{
    private const DEFAULT_NEW_GAME_LIMIT = 8;
    private const DEFAULT_NEW_DLCS_LIMIT = 8;
    private const DEFAULT_POPULAR_GAME_LIMIT = 10;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getNewGames(int $limit = self::DEFAULT_NEW_GAME_LIMIT): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                *
            FROM
                trophy_title
            WHERE
                `status` != 2
            ORDER BY
                id DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNewDlcs(int $limit = self::DEFAULT_NEW_DLCS_LIMIT): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
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
            WHERE
                tt.status != 2
                AND tg.group_id != 'default'
            ORDER BY
                tg.id DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPopularGames(int $limit = self::DEFAULT_POPULAR_GAME_LIMIT): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                id,
                icon_url,
                platform,
                `name`,
                recent_players
            FROM
                trophy_title
            WHERE
                `status` != 2
            ORDER BY
                recent_players DESC
            LIMIT
                :limit
            SQL
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}
