<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameObsoleteReplacement.php';
require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/Game/GameHeaderData.php';
require_once __DIR__ . '/Game/GameHeaderParent.php';
require_once __DIR__ . '/Game/GameHeaderStack.php';

class GameHeaderService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
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

        return new GameHeaderData($parentGame, $stacks, $unobtainableTrophyCount, $obsoleteReplacements);
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
}
