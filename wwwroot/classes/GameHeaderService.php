<?php

declare(strict_types=1);

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

    /**
     * @param array<string, mixed> $game
     */
    public function buildHeaderData(array $game): GameHeaderData
    {
        $status = (int) ($game['status'] ?? 0);
        $npCommunicationId = (string) ($game['np_communication_id'] ?? '');
        $parentNpCommunicationId = $game['parent_np_communication_id'] ?? null;

        $parentGame = null;
        if ($status === 2 && is_string($parentNpCommunicationId) && $parentNpCommunicationId !== '') {
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

        return new GameHeaderData($parentGame, $stacks, $unobtainableTrophyCount);
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
                id,
                `name`,
                platform,
                region
            FROM
                trophy_title
            WHERE
                parent_np_communication_id = :parent_np_communication_id
            ORDER BY
                `name`,
                platform,
                region
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
                trophy
            WHERE
                `status` = 1
                AND np_communication_id = :np_communication_id
            SQL
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        return (int) $query->fetchColumn();
    }
}
