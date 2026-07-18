<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';
require_once __DIR__ . '/NestedDatabaseTransactionRunner.php';

/**
 * Persists trophy-title merge metadata: merged status, parent links, platform union, and changelog rows.
 */
final class TrophyMergeMetadataRepository
{
    private const array PLATFORM_ORDER = ['PS3', 'PSVITA', 'PS4', 'PSVR', 'PS5', 'PSVR2', 'PC'];

    public function __construct(
        private readonly PDO $database,
        private readonly NestedDatabaseTransactionRunner $transactionRunner
    ) {
    }

    public function markGameAsMergedByNpId(string $npCommunicationId): void
    {
        $this->transactionRunner->execute(function () use ($npCommunicationId): void {
            $query = $this->database->prepare(
                <<<'SQL'
                UPDATE trophy_title_meta
                SET    status = 2
                WHERE  np_communication_id = :child_np_communication_id
                SQL
            );
            $query->bindValue(':child_np_communication_id', $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
        });
    }

    public function markGameAsMergedById(int $gameId): void
    {
        $this->transactionRunner->execute(function () use ($gameId): void {
            $lookup = $this->database->prepare(
                <<<'SQL'
                SELECT np_communication_id
                FROM   trophy_title
                WHERE  id = :game_id
                SQL
            );
            $lookup->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $lookup->execute();

            $npCommunicationId = $lookup->fetchColumn();

            if ($npCommunicationId === false || $npCommunicationId === null) {
                return;
            }

            $query = $this->database->prepare(
                <<<'SQL'
                UPDATE trophy_title_meta
                SET    status = 2
                WHERE  np_communication_id = :np_communication_id
                SQL
            );
            $query->bindValue(':np_communication_id', (string) $npCommunicationId, PDO::PARAM_STR);
            $query->execute();
        });
    }

    public function updateParentRelationship(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(
            'UPDATE trophy_title_meta SET parent_np_communication_id = :parent_np_communication_id WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        if ($query->rowCount() === 0) {
            $metaExists = $this->database->prepare(
                'SELECT 1 FROM trophy_title_meta WHERE np_communication_id = :np_communication_id'
            );
            $metaExists->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
            $metaExists->execute();

            if ($metaExists->fetchColumn() === false) {
                $metaInsert = $this->database->prepare(
                    <<<'SQL'
                    INSERT INTO trophy_title_meta (
                        np_communication_id,
                        message,
                        parent_np_communication_id,
                        status
                    ) VALUES (
                        :np_communication_id,
                        '',
                        :parent_np_communication_id,
                        2
                    )
SQL
                );
                $metaInsert->bindValue(':np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
                $metaInsert->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
                $metaInsert->execute();
            }
        }

        $this->updateParentPlatform($parentNpCommunicationId, $childNpCommunicationId);
    }

    public function logChange(string $changeType, int $param1, int $param2): void
    {
        $query = $this->database->prepare(
            'INSERT INTO `psn100_change` (`change_type`, `param_1`, `param_2`) VALUES (:change_type, :param_1, :param_2)'
        );
        $query->bindValue(':change_type', $changeType, PDO::PARAM_STR);
        $query->bindValue(':param_1', $param1, PDO::PARAM_INT);
        $query->bindValue(':param_2', $param2, PDO::PARAM_INT);
        $query->execute();
    }

    private function updateParentPlatform(string $parentNpCommunicationId, string $childNpCommunicationId): void
    {
        $parentPlatforms = $this->getPlatformsByNpCommunicationId($parentNpCommunicationId);
        $childPlatforms = $this->getPlatformsByNpCommunicationId($childNpCommunicationId);

        if ($childPlatforms === []) {
            return;
        }

        $platformLookup = [];
        foreach ($parentPlatforms as $platform) {
            if ($platform === '') {
                continue;
            }

            $platformLookup[$platform] = true;
        }

        $updated = false;

        foreach ($childPlatforms as $platform) {
            if ($platform === '') {
                continue;
            }

            if (!isset($platformLookup[$platform])) {
                $platformLookup[$platform] = true;
                $updated = true;
            }
        }

        if (!$updated) {
            return;
        }

        $sortedPlatforms = $this->sortPlatforms(array_keys($platformLookup));

        $query = $this->database->prepare(
            'UPDATE trophy_title SET platform = :platform WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':platform', implode(',', $sortedPlatforms), PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    /**
     * @return list<string>
     */
    private function getPlatformsByNpCommunicationId(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT platform FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $platforms = $query->fetchColumn();

        if ($platforms === false || $platforms === null || $platforms === '') {
            return [];
        }

        return CommaSeparatedValues::parseTrimmed((string) $platforms);
    }

    /**
     * @param list<string> $platforms
     *
     * @return list<string>
     */
    private function sortPlatforms(array $platforms): array
    {
        $order = array_flip(self::PLATFORM_ORDER);

        usort(
            $platforms,
            static function (string $left, string $right) use ($order): int {
                $leftOrder = $order[$left] ?? PHP_INT_MAX;
                $rightOrder = $order[$right] ?? PHP_INT_MAX;

                if ($leftOrder === $rightOrder) {
                    return strcmp($left, $right);
                }

                return $leftOrder <=> $rightOrder;
            }
        );

        return $platforms;
    }
}
