<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameObsoleteReplacement.php';
require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/Game/GameHeaderData.php';
require_once __DIR__ . '/Game/GameHeaderParent.php';
require_once __DIR__ . '/Game/GameHeaderStack.php';
require_once __DIR__ . '/PsnpPlusClient.php';

class GameHeaderService
{
    private PDO $database;

    private PsnpPlusClient $psnpPlusClient;

    public function __construct(PDO $database, ?PsnpPlusClient $psnpPlusClient = null)
    {
        $this->database = $database;
        $this->psnpPlusClient = $psnpPlusClient ?? new PsnpPlusClient();
    }

    public function buildHeaderData(GameDetails $game): GameHeaderData
    {
        $status = $game->getStatus();
        $npCommunicationId = $game->getNpCommunicationId();
        $parentNpCommunicationId = $game->getParentNpCommunicationId();

        $parentGame = null;
        if ($status === 2 && $parentNpCommunicationId !== null && $parentNpCommunicationId !== '') {
            $parentGame = $this->fetchParentGame($parentNpCommunicationId);
        }

        $stacks = [];
        if ($npCommunicationId !== '' && str_starts_with($npCommunicationId, 'MERGE')) {
            $stacks = $this->fetchStacks($npCommunicationId);
        }

        $unobtainableTrophyCount = 0;
        if ($npCommunicationId !== '') {
            $unobtainableTrophyCount = $this->countUnobtainableTrophies($npCommunicationId);
        }

        $obsoleteReplacements = [];
        if ($game->hasObsoleteReplacements()) {
            $obsoleteReplacements = $this->fetchObsoleteReplacements($game->getObsoleteGameIds());
        }

        $psnpPlusNote = $this->findPsnpPlusNote($game);

        return new GameHeaderData($parentGame, $stacks, $unobtainableTrophyCount, $obsoleteReplacements, $psnpPlusNote);
    }

    private function fetchParentGame(string $npCommunicationId): ?GameHeaderParent
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                id,
                `name`
            FROM
                trophy_title
            WHERE
                np_communication_id = :np_communication_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return GameHeaderParent::fromArray($row);
    }

    /**
     * @return GameHeaderStack[]
     */
    private function fetchStacks(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                tt.id,
                tt.`name`,
                tt.platform,
                ttm.region
            FROM
                trophy_title tt
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                ttm.parent_np_communication_id = :parent_np_communication_id
            ORDER BY
                tt.`name`,
                tt.platform,
                ttm.region
            SQL
        );
        $query->bindValue(':parent_np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static fn(array $row): GameHeaderStack => GameHeaderStack::fromArray($row),
            $rows
        );
    }

    private function countUnobtainableTrophies(string $npCommunicationId): int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                trophy t
                JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE
                tm.status = 1
                AND t.np_communication_id = :np_communication_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @param int[] $ids
     * @return GameObsoleteReplacement[]
     */
    private function fetchObsoleteReplacements(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = $this->database->prepare(
            sprintf(
                'SELECT id, `name` FROM trophy_title WHERE id IN (%s)',
                $placeholders
            )
        );

        foreach ($ids as $index => $id) {
            $query->bindValue($index + 1, $id, PDO::PARAM_INT);
        }

        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $replacementsById = [];
        foreach ($rows as $row) {
            $replacement = GameObsoleteReplacement::fromArray($row);
            $replacementsById[$replacement->getId()] = $replacement;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($replacementsById[$id])) {
                $ordered[] = $replacementsById[$id];
            }
        }

        return $ordered;
    }

    private function findPsnpPlusNote(GameDetails $game): ?string
    {
        $psnprofilesId = $game->getPsnprofilesId();
        if ($psnprofilesId !== null) {
            $note = $this->getPsnpPlusNote($psnprofilesId);
            if ($note !== null) {
                return $note;
            }
        }

        $parentNpCommunicationId = $game->getParentNpCommunicationId();
        if ($parentNpCommunicationId !== null && $parentNpCommunicationId !== '') {
            $parentPsnprofilesId = $this->findPsnprofilesId($parentNpCommunicationId);
            if ($parentPsnprofilesId !== null && $parentPsnprofilesId !== $psnprofilesId) {
                $note = $this->getPsnpPlusNote($parentPsnprofilesId);
                if ($note !== null) {
                    return $note;
                }
            }
        }

        $npCommunicationId = $game->getNpCommunicationId();
        if ($npCommunicationId !== '' && str_starts_with($npCommunicationId, 'MERGE')) {
            $childPsnprofilesIds = $this->findChildPsnprofilesIds($npCommunicationId);
            foreach ($childPsnprofilesIds as $childPsnprofilesId) {
                $note = $this->getPsnpPlusNote($childPsnprofilesId);
                if ($note !== null) {
                    return $note;
                }
            }
        }

        return null;
    }

    private function findPsnprofilesId(string $npCommunicationId): ?int
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                psnprofiles_id
            FROM
                trophy_title_meta
            WHERE
                np_communication_id = :np_communication_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $psnprofilesId = $query->fetchColumn();

        if ($psnprofilesId === false) {
            return null;
        }

        $stringValue = (string) $psnprofilesId;

        return ctype_digit($stringValue) ? (int) $stringValue : null;
    }

    /**
     * @return int[]
     */
    private function findChildPsnprofilesIds(string $parentNpCommunicationId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                psnprofiles_id
            FROM
                trophy_title_meta
            WHERE
                parent_np_communication_id = :parent_np_communication_id
                AND psnprofiles_id IS NOT NULL
            ORDER BY
                CASE
                    WHEN region = 'NA' THEN 0
                    WHEN region = 'EU' THEN 1
                    WHEN region IS NULL THEN 2
                    WHEN region = 'HK' THEN 3
                    WHEN region = 'JP' THEN 4
                    WHEN region = 'AS' THEN 5
                    ELSE 6
                END,
                region,
                psnprofiles_id
            SQL
        );
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($rows)) {
            return [];
        }

        $psnprofilesIds = [];
        foreach ($rows as $psnprofilesId) {
            $stringValue = (string) $psnprofilesId;
            if ($stringValue === '' || !ctype_digit($stringValue)) {
                continue;
            }

            $psnprofilesIds[] = (int) $stringValue;
        }

        return array_values(array_unique($psnprofilesIds));
    }

    private function getPsnpPlusNote(int $psnprofilesId): ?string
    {
        try {
            return $this->psnpPlusClient->getNote($psnprofilesId);
        } catch (RuntimeException) {
            return null;
        }
    }
}
