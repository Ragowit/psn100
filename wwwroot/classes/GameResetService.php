<?php

declare(strict_types=1);

class GameResetService
{
    private const int ACTION_RESET = 0;
    private const int ACTION_DELETE = 1;

    public function __construct(private readonly PDO $database)
    {
    }

    public function process(int $gameId, int $action): string
    {
        $npCommunicationId = $this->getGameNpCommunicationId($gameId);

        if ($npCommunicationId === null) {
            throw new InvalidArgumentException('Can only reset/delete merged game entries.');
        }

        if (!str_starts_with($npCommunicationId, 'MERGE')) {
            throw new InvalidArgumentException('Can only reset/delete merged game entries.');
        }

        return match ($action) {
            self::ACTION_RESET => $this->resetGame($gameId, $npCommunicationId),
            self::ACTION_DELETE => $this->deleteGame($gameId, $npCommunicationId),
            default => throw new InvalidArgumentException('Unknown method.'),
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
            $this->executeStatement(
                'DELETE FROM trophy_merge WHERE parent_np_communication_id = :np_communication_id',
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
                'UPDATE trophy_title_meta SET owners = 0, owners_completed = 0 WHERE np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
            $this->executeStatement(
                'UPDATE trophy_title_meta SET parent_np_communication_id = NULL WHERE parent_np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
        });

        $this->logChange('GAME_RESET', $gameId);

        return sprintf('Game %d was reset.', $gameId);
    }

    private function deleteGame(int $gameId, string $npCommunicationId): string
    {
        $gameName = $this->getGameName($gameId) ?? '';

        $this->executeWithinTransaction(function () use ($npCommunicationId): void {
            $this->executeStatement(
                'DELETE FROM trophy_merge WHERE parent_np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
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
            $this->executeStatement(
                'UPDATE trophy_title_meta SET parent_np_communication_id = NULL WHERE parent_np_communication_id = :np_communication_id',
                [':np_communication_id' => $npCommunicationId]
            );
        });

        $this->logChange('GAME_DELETE', $gameId, $gameName);

        return sprintf('Game %d was deleted.', $gameId);
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

    private function logChange(string $changeType, int $gameId, ?string $extra = null): void
    {
        if ($extra === null) {
            $query = $this->database->prepare('INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (:change_type, :param_1)');
            $query->bindValue(':change_type', $changeType, PDO::PARAM_STR);
            $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
        } else {
            $query = $this->database->prepare('INSERT INTO `psn100_change` (`change_type`, `param_1`, `extra`) VALUES (:change_type, :param_1, :extra)');
            $query->bindValue(':change_type', $changeType, PDO::PARAM_STR);
            $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
            $query->bindValue(':extra', $extra, PDO::PARAM_STR);
        }

        $query->execute();
    }

    private function executeWithinTransaction(callable $callback): void
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
