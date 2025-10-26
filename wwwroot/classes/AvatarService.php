<?php

declare(strict_types=1);

require_once __DIR__ . '/Avatar.php';

class AvatarService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getTotalUniqueAvatarCount(): int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(DISTINCT avatar_url)
            FROM
                player p
            WHERE
                p.status = 0
            SQL
        );
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @return Avatar[]
     */
    public function getAvatars(int $page, int $limit): array
    {
        $limit = max($limit, 1);
        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(*) AS count,
                avatar_url
            FROM
                player p
            WHERE
                p.status = 0
            GROUP BY
                avatar_url
            ORDER BY
                count DESC,
                avatar_url
            LIMIT
                :offset, :limit
            SQL
        );
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        $avatars = [];
        foreach ($rows as $row) {
            $avatarUrl = isset($row['avatar_url']) ? (string) $row['avatar_url'] : '';
            $count = isset($row['count']) ? (int) $row['count'] : 0;
            $avatars[] = new Avatar($avatarUrl, $count);
        }

        return $avatars;
    }
}
