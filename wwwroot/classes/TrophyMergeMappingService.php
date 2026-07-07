<?php

declare(strict_types=1);

/**
 * Matches child trophies to parent merge-title trophies and persists trophy_merge rows.
 */
final class TrophyMergeMappingService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function insertMappingsByName(int $childGameId, int $parentGameId): string
    {
        $message = '';

        $childTrophies = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id,
                   group_id,
                   order_id,
                   name
            FROM   trophy
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy_title
                                          WHERE  id = :child_game_id)
SQL
        );
        $childTrophies->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $childTrophies->execute();

        $parentTrophies = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id,
                   group_id,
                   order_id,
                   name
            FROM   trophy
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy_title
                                          WHERE  id = :parent_game_id)
SQL
        );
        $parentTrophies->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
        $parentTrophies->execute();

        $parentTrophyByName = [];

        while ($parentTrophy = $parentTrophies->fetch(PDO::FETCH_ASSOC)) {
            $name = $this->normalizeTrophyName((string) $parentTrophy['name']);
            $parentTrophyByName[$name][] = $parentTrophy;
        }

        while ($childTrophy = $childTrophies->fetch(PDO::FETCH_ASSOC)) {
            $childName = $this->normalizeTrophyName((string) $childTrophy['name']);
            $parentTrophy = $parentTrophyByName[$childName] ?? [];

            if (count($parentTrophy) === 1) {
                $this->insertDirectMapping($childTrophy, $parentTrophy[0]);
            } else {
                $message .= $childTrophy['name'] . " couldn't be merged.<br>";
            }
        }

        return $message;
    }

    public function insertMappingsByIcon(int $childGameId, int $parentGameId): string
    {
        $message = '';

        $childTrophies = $this->database->prepare(
            <<<'SQL'
            SELECT
                t.np_communication_id,
                t.group_id,
                t.order_id,
                t.name,
                t.icon_url,
                tc.counter
            FROM
                trophy t,
                (
                SELECT
                    icon_url,
                    COUNT(icon_url) AS counter
                FROM
                    trophy
                WHERE
                    np_communication_id =(
                    SELECT
                        np_communication_id
                    FROM
                        trophy_title
                    WHERE
                        id = :child_game_id
                )
            GROUP BY
                icon_url
            ) AS tc
            WHERE
                t.icon_url = tc.icon_url AND t.np_communication_id =(
                SELECT
                    np_communication_id
                FROM
                    trophy_title
                WHERE
                    id = :child_game_id
            );
SQL
        );
        $childTrophies->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $childTrophies->execute();

        while ($childTrophy = $childTrophies->fetch(PDO::FETCH_ASSOC)) {
            $parentTrophies = $this->database->prepare(
                <<<'SQL'
                SELECT np_communication_id,
                       group_id,
                       order_id
                FROM   trophy
                WHERE  np_communication_id = (SELECT np_communication_id
                                              FROM   trophy_title
                                              WHERE  id = :parent_game_id)
                       AND icon_url = :icon_url
SQL
            );
            $parentTrophies->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
            $parentTrophies->bindValue(':icon_url', $childTrophy['icon_url'], PDO::PARAM_STR);
            $parentTrophies->execute();

            $parentTrophy = $parentTrophies->fetchAll(PDO::FETCH_ASSOC);

            if ((int) $childTrophy['counter'] === 1 && count($parentTrophy) === 1) {
                $this->insertDirectMapping($childTrophy, $parentTrophy[0]);
            } else {
                $message .= $childTrophy['name'] . " couldn't be merged.<br>";
            }
        }

        return $message;
    }

    public function insertMappingsByOrder(int $childGameId, int $parentGameId): void
    {
        $query = $this->database->prepare(
            <<<'SQL'
            INSERT IGNORE
            into   trophy_merge
                   (
                          child_np_communication_id,
                          child_group_id,
                          child_order_id,
                          parent_np_communication_id,
                          parent_group_id,
                          parent_order_id
                   )
            SELECT     child.np_communication_id,
                       child.group_id,
                       child.order_id,
                       parent.np_communication_id,
                       parent.group_id,
                       parent.order_id
            FROM       trophy child
            INNER JOIN trophy parent
            USING      (group_id, order_id)
            WHERE      child.np_communication_id =
                       (
                              SELECT np_communication_id
                              FROM   trophy_title
                              WHERE  id = :child_game_id)
            AND        parent.np_communication_id =
                       (
                              SELECT np_communication_id
                              FROM   trophy_title
                              WHERE  id = :parent_game_id)
SQL
        );
        $query->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $query->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
        $query->execute();
    }

    private function normalizeTrophyName(string $name): string
    {
        $name = trim($name);

        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * @param array{np_communication_id:string, group_id:string, order_id:int|string, name?:string} $childTrophy
     * @param array{np_communication_id:string, group_id:string, order_id:int|string} $parentTrophy
     */
    private function insertDirectMapping(array $childTrophy, array $parentTrophy): void
    {
        $query = $this->database->prepare(
            <<<'SQL'
            INSERT IGNORE
            into   trophy_merge
                   (
                          child_np_communication_id,
                          child_group_id,
                          child_order_id,
                          parent_np_communication_id,
                          parent_group_id,
                          parent_order_id
                   )
                   VALUES
                   (
                          :child_np_communication_id,
                          :child_group_id,
                          :child_order_id,
                          :parent_np_communication_id,
                          :parent_group_id,
                          :parent_order_id
                   )
SQL
        );
        $query->bindValue(':child_np_communication_id', $childTrophy['np_communication_id'], PDO::PARAM_STR);
        $query->bindValue(':child_group_id', $childTrophy['group_id'], PDO::PARAM_STR);
        $query->bindValue(':child_order_id', (int) $childTrophy['order_id'], PDO::PARAM_INT);
        $query->bindValue(':parent_np_communication_id', $parentTrophy['np_communication_id'], PDO::PARAM_STR);
        $query->bindValue(':parent_group_id', $parentTrophy['group_id'], PDO::PARAM_STR);
        $query->bindValue(':parent_order_id', (int) $parentTrophy['order_id'], PDO::PARAM_INT);
        $query->execute();
    }
}
