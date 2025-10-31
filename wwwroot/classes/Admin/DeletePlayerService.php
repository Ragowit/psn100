<?php

declare(strict_types=1);

final class DeletePlayerService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    /**
     * @return array{account_id: string, online_id: ?string}|null
     */
    public function findPlayerByAccountId(string $accountId): ?array
    {
        $query = $this->database->prepare('SELECT account_id, online_id FROM player WHERE account_id = :account_id');
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->execute();

        /** @var array{account_id?: string, online_id?: string|null}|false $player */
        $player = $query->fetch(PDO::FETCH_ASSOC);

        if ($player === false) {
            return null;
        }

        return [
            'account_id' => (string) $player['account_id'],
            'online_id' => array_key_exists('online_id', $player) ? ($player['online_id'] === null ? null : (string) $player['online_id']) : null,
        ];
    }

    /**
     * @return array{account_id: string, online_id: ?string}|null
     */
    public function findPlayerByOnlineId(string $onlineId): ?array
    {
        $query = $this->database->prepare('SELECT account_id, online_id FROM player WHERE online_id = :online_id');
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        /** @var array{account_id?: string, online_id?: string|null}|false $player */
        $player = $query->fetch(PDO::FETCH_ASSOC);

        if ($player === false) {
            return null;
        }

        return [
            'account_id' => (string) $player['account_id'],
            'online_id' => array_key_exists('online_id', $player) ? ($player['online_id'] === null ? null : (string) $player['online_id']) : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function deletePlayerByAccountId(string $accountId): array
    {
        if (!$this->database->beginTransaction()) {
            throw new RuntimeException('Failed to start database transaction.');
        }

        try {
            $counts = [];
            $counts['trophy_earned'] = $this->deleteByAccountId(
                'DELETE FROM trophy_earned WHERE account_id = :account_id',
                $accountId
            );
            $counts['trophy_group_player'] = $this->deleteByAccountId(
                'DELETE FROM trophy_group_player WHERE account_id = :account_id',
                $accountId
            );
            $counts['trophy_title_player'] = $this->deleteByAccountId(
                'DELETE FROM trophy_title_player WHERE account_id = :account_id',
                $accountId
            );
            $counts['player'] = $this->deleteByAccountId(
                'DELETE FROM player WHERE account_id = :account_id',
                $accountId
            );

            $logStatement = $this->database->prepare('DELETE FROM log WHERE message LIKE :message');
            $logStatement->bindValue(':message', '%' . $accountId . '%', PDO::PARAM_STR);
            $logStatement->execute();
            $counts['log'] = (int) $logStatement->rowCount();

            $this->database->commit();

            return $counts;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw new RuntimeException('Failed to delete player data.', 0, $exception);
        }
    }

    private function deleteByAccountId(string $sql, string $accountId): int
    {
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $statement->execute();

        return (int) $statement->rowCount();
    }
}
