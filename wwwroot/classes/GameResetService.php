<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';
require_once __DIR__ . '/GameAvailabilityStatus.php';
require_once __DIR__ . '/GameResetAction.php';
require_once __DIR__ . '/MergeNpCommunicationId.php';

final readonly class GameResetService
{
    public function __construct(final private PDO $database)
    {
    }

    public function process(int $gameId, GameResetAction $action): string
    {
        $npCommunicationId = $this->getGameNpCommunicationId($gameId);

        if ($npCommunicationId === null) {
            throw new InvalidArgumentException('Can only reset/delete merged game entries.');
        }

        if (!MergeNpCommunicationId::matches($npCommunicationId)) {
            throw new InvalidArgumentException('Can only reset/delete merged game entries.');
        }

        return match ($action) {
            GameResetAction::RESET => $this->resetGame($gameId, $npCommunicationId),
            GameResetAction::DELETE => $this->deleteGame($gameId, $npCommunicationId),
        };
    }

    private function getGameNpCommunicationId(int $gameId): ?string
    {
        $query = $this->database->prepare('SELECT np_communication_id FROM trophy_title WHERE id = :game_id');
        $query->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $npCommunicationId = $query->fetchColumn();

        return $npCommunicationId === false ? null : (string) $npCommunicationId;
    }

    private function resetGame(int $gameId, string $npCommunicationId): string
    {
        $this->executeWithinTransaction(function () use ($npCommunicationId): void {
            $this->detachChildrenFromParent($npCommunicationId);
            // Per-partition deletes on trophy_earned (see deleteTrophyEarnedForTitle).
            $this->deleteTrophyEarnedForTitle($npCommunicationId);
            $this->executeStatement(
                'DELETE FROM trophy_group_player WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            $this->executeStatement(
                'DELETE FROM trophy_title_player WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            $this->executeStatement(
                'UPDATE trophy_title_meta SET owners = 0, owners_completed = 0 WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
        });

        $this->logChange(ChangelogEntryType::GAME_RESET, $gameId);

        return sprintf('Game %d was reset.', $gameId);
    }

    private function deleteGame(int $gameId, string $npCommunicationId): string
    {
        $gameName = $this->getGameName($gameId) ?? '';

        $this->executeWithinTransaction(function () use ($npCommunicationId): void {
            // Unmerge children before deleting the parent title so they are not left hidden as MERGED.
            $this->detachChildrenFromParent($npCommunicationId);
            $this->executeStatement(
                'DELETE FROM trophy WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            // Per-partition deletes on trophy_earned (see deleteTrophyEarnedForTitle).
            $this->deleteTrophyEarnedForTitle($npCommunicationId);
            $this->executeStatement(
                'DELETE FROM trophy_group_player WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            $this->executeStatement(
                'DELETE FROM trophy_title_player WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            $this->executeStatement(
                'DELETE FROM trophy_group WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            $this->executeStatement(
                'DELETE FROM trophy_title WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
        });

        $this->logChange(ChangelogEntryType::GAME_DELETE, $gameId, $gameName);

        return sprintf('Game %d was deleted.', $gameId);
    }

    /**
     * Remove this parent's trophy_merge mappings, clear parent links, and restore orphaned
     * MERGED children to NORMAL. Includes trophy-only merges that never set parent_np_communication_id.
     */
    private function detachChildrenFromParent(string $npCommunicationId): void
    {
        $childNpCommunicationIds = $this->collectChildNpCommunicationIds($npCommunicationId);

        $this->executeStatement(
            'DELETE FROM trophy_merge WHERE parent_np_communication_id = :np_communication_id',
            [':np_communication_id' => $npCommunicationId]
        );

        $this->executeStatement(
            'UPDATE trophy_title_meta SET parent_np_communication_id = NULL WHERE parent_np_communication_id = :np_communication_id',
            [':np_communication_id' => $npCommunicationId]
        );

        $this->restoreOrphanedMergedChildren($childNpCommunicationIds);
    }

    /**
     * @return list<string>
     */
    private function collectChildNpCommunicationIds(string $parentNpCommunicationId): array
    {
        $childIds = [];

        $mappedChildren = $this->database->prepare(
            <<<'SQL'
            SELECT DISTINCT child_np_communication_id
            FROM trophy_merge
            WHERE parent_np_communication_id = :np_communication_id
            SQL
        );
        $mappedChildren->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $mappedChildren->execute();

        while (($childId = $mappedChildren->fetchColumn()) !== false) {
            $childIds[(string) $childId] = true;
        }

        $linkedChildren = $this->database->prepare(
            <<<'SQL'
            SELECT np_communication_id
            FROM trophy_title_meta
            WHERE parent_np_communication_id = :np_communication_id
            SQL
        );
        $linkedChildren->bindValue(':np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $linkedChildren->execute();

        while (($childId = $linkedChildren->fetchColumn()) !== false) {
            $childIds[(string) $childId] = true;
        }

        return array_keys($childIds);
    }

    /**
     * @param list<string> $childNpCommunicationIds
     */
    private function restoreOrphanedMergedChildren(array $childNpCommunicationIds): void
    {
        if ($childNpCommunicationIds === []) {
            return;
        }

        $mergedStatus = GameAvailabilityStatus::MERGED->value;
        $normalStatus = GameAvailabilityStatus::NORMAL->value;
        $placeholders = [];
        $parameters = [];

        foreach ($childNpCommunicationIds as $index => $childNpCommunicationId) {
            $placeholder = ':child_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $childNpCommunicationId;
        }

        $placeholderList = implode(', ', $placeholders);

        $this->executeStatement(
            <<<SQL
            UPDATE trophy_title_meta
            SET status = {$normalStatus}
            WHERE np_communication_id IN ({$placeholderList})
              AND status = {$mergedStatus}
              AND NOT EXISTS (
                  SELECT 1
                  FROM trophy_merge
                  WHERE trophy_merge.child_np_communication_id = trophy_title_meta.np_communication_id
              )
            SQL,
            $parameters
        );
    }

    /**
     * Delete all trophy_earned rows for a title without one statement spanning every partition.
     *
     * trophy_earned is PARTITION BY HASH(account_id) with 256 partitions (~billions of rows).
     * A bare DELETE ... WHERE np_communication_id = ? opens all partitions at once. Issuing
     * one DELETE per partition keeps each statement partition-local while still removing
     * every matching row (including orphans not present in trophy_title_player).
     */
    private function deleteTrophyEarnedForTitle(string $npCommunicationId): void
    {
        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            for ($partition = 0; $partition < 256; $partition++) {
                $this->executeStatement(
                    sprintf(
                        'DELETE FROM trophy_earned PARTITION (p%d) WHERE np_communication_id = :np_communication_id',
                        $partition
                    ),
                    [':np_communication_id' => $npCommunicationId]
                );
            }

            return;
        }

        // Non-MySQL drivers (SQLite tests) have no HASH partitions.
        $this->executeStatement(
            'DELETE FROM trophy_earned WHERE np_communication_id = :np_communication_id',
            [':np_communication_id' => $npCommunicationId]
        );
    }

    private function getGameName(int $gameId): ?string
    {
        $query = $this->database->prepare('SELECT `name` FROM trophy_title WHERE id = :game_id');
        $query->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $gameName = $query->fetchColumn();

        return $gameName === false ? null : (string) $gameName;
    }

    private function logChange(ChangelogEntryType $changeType, int $gameId, ?string $extra = null): void
    {
        if ($extra === null) {
            $query = $this->database->prepare('INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (:change_type, :param_1)');
            $query->bindValue(':change_type', $changeType->value, PDO::PARAM_STR);
            $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
        } else {
            $query = $this->database->prepare('INSERT INTO `psn100_change` (`change_type`, `param_1`, `extra`) VALUES (:change_type, :param_1, :extra)');
            $query->bindValue(':change_type', $changeType->value, PDO::PARAM_STR);
            $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
            $query->bindValue(':extra', $extra, PDO::PARAM_STR);
        }

        $query->execute();
    }

    private function executeWithinTransaction(\Closure $callback): void
    {
        $this->database->beginTransaction();

        try {
            $callback();
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function executeStatement(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);

        foreach ($parameters as $parameter => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($parameter, $value, $type);
        }

        $statement->execute();
    }
}
