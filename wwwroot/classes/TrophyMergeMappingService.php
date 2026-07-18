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
            WITH child_title AS (
                SELECT np_communication_id
                FROM trophy_title
                WHERE id = :child_game_id
            )
            SELECT t.np_communication_id,
                   t.group_id,
                   t.order_id,
                   t.name
            FROM trophy t
            INNER JOIN child_title ct ON t.np_communication_id = ct.np_communication_id
SQL
        );
        $childTrophies->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $childTrophies->execute();

        $parentTrophies = $this->database->prepare(
            <<<'SQL'
            WITH parent_title AS (
                SELECT np_communication_id
                FROM trophy_title
                WHERE id = :parent_game_id
            )
            SELECT t.np_communication_id,
                   t.group_id,
                   t.order_id,
                   t.name
            FROM trophy t
            INNER JOIN parent_title pt ON t.np_communication_id = pt.np_communication_id
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
                $this->insertDirectMapping($childTrophy, array_first($parentTrophy));
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
            WITH child_title AS (
                SELECT np_communication_id
                FROM trophy_title
                WHERE id = :child_game_id
            ),
            child_icon_counts AS (
                SELECT t.icon_url, COUNT(*) AS counter
                FROM trophy t
                INNER JOIN child_title ct ON t.np_communication_id = ct.np_communication_id
                GROUP BY t.icon_url
            )
            SELECT
                t.np_communication_id,
                t.group_id,
                t.order_id,
                t.name,
                t.icon_url,
                cic.counter
            FROM trophy t
            INNER JOIN child_title ct ON t.np_communication_id = ct.np_communication_id
            INNER JOIN child_icon_counts cic ON cic.icon_url = t.icon_url
SQL
        );
        $childTrophies->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $childTrophies->execute();

        $parentTrophies = $this->database->prepare(
            <<<'SQL'
            WITH parent_title AS (
                SELECT np_communication_id
                FROM trophy_title
                WHERE id = :parent_game_id
            )
            SELECT t.np_communication_id,
                   t.group_id,
                   t.order_id,
                   t.icon_url
            FROM trophy t
            INNER JOIN parent_title pt ON t.np_communication_id = pt.np_communication_id
SQL
        );
        $parentTrophies->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
        $parentTrophies->execute();

        $parentTrophiesByIcon = [];

        while ($parentTrophy = $parentTrophies->fetch(PDO::FETCH_ASSOC)) {
            $parentTrophiesByIcon[(string) $parentTrophy['icon_url']][] = $parentTrophy;
        }

        while ($childTrophy = $childTrophies->fetch(PDO::FETCH_ASSOC)) {
            $parentTrophy = $parentTrophiesByIcon[(string) $childTrophy['icon_url']] ?? [];

            if ((int) $childTrophy['counter'] === 1 && count($parentTrophy) === 1) {
                $this->insertDirectMapping($childTrophy, array_first($parentTrophy));
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
            WITH child_title AS (
                SELECT np_communication_id
                FROM trophy_title
                WHERE id = :child_game_id
            ),
            parent_title AS (
                SELECT np_communication_id
                FROM trophy_title
                WHERE id = :parent_game_id
            )
            SELECT     child.np_communication_id,
                       child.group_id,
                       child.order_id,
                       parent.np_communication_id,
                       parent.group_id,
                       parent.order_id
            FROM       trophy child
            INNER JOIN child_title ct ON child.np_communication_id = ct.np_communication_id
            INNER JOIN trophy parent
            USING      (group_id, order_id)
            INNER JOIN parent_title pt ON parent.np_communication_id = pt.np_communication_id
SQL
        );
        $query->bindValue(':child_game_id', $childGameId, PDO::PARAM_INT);
        $query->bindValue(':parent_game_id', $parentGameId, PDO::PARAM_INT);
        $query->execute();
    }

    private function normalizeTrophyName(string $name): string
    {
        return $name
            |> trim(...)
            |> (fn(string $value): string => mb_strtolower($value, 'UTF-8'));
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
