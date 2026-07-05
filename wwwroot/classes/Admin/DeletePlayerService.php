<?php

declare(strict_types=1);

final class DeletePlayerService
{
    public function __construct(private readonly PDO $database)
    {
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
            $player = $this->findPlayerByAccountId($accountId);
            $onlineId = $player['online_id'] ?? null;

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
            $counts['player_ranking'] = $this->deleteByAccountId(
                'DELETE FROM player_ranking WHERE account_id = :account_id',
                $accountId
            );
            $counts['player_report'] = $this->deleteByAccountId(
                'DELETE FROM player_report WHERE account_id = :account_id',
                $accountId
            );
            $counts['player_queue'] = $onlineId !== null && $onlineId !== ''
                ? $this->deleteByOnlineId('DELETE FROM player_queue WHERE online_id = :online_id', $onlineId)
                : 0;
            $counts['player'] = $this->deleteByAccountId(
                'DELETE FROM player WHERE account_id = :account_id',
                $accountId
            );
            $counts['log'] = $this->deleteLogMessagesForAccountId($accountId);

            $this->database->commit();

            return $counts;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw new RuntimeException('Failed to delete player data.', 0, $exception);
        }
    }

    private function deleteLogMessagesForAccountId(string $accountId): int
    {
        $statement = $this->database->prepare(
            'DELETE FROM log WHERE message LIKE :pattern_parentheses'
        );
        $statement->bindValue(':pattern_parentheses', '%(' . $accountId . ')%', PDO::PARAM_STR);
        $statement->execute();

        return (int) $statement->rowCount();
    }

    private function deleteByAccountId(string $sql, string $accountId): int
    {
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $statement->execute();

        return (int) $statement->rowCount();
    }

    private function deleteByOnlineId(string $sql, string $onlineId): int
    {
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $statement->execute();

        return (int) $statement->rowCount();
    }
}
