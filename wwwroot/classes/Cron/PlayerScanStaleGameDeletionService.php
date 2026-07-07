<?php

declare(strict_types=1);

/**
 * Removes local trophy progress for games no longer returned by the PlayStation API.
 *
 * Encapsulates missing-game deletion logic that was previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerScanStaleGameDeletionService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function countLocalGames(int $accountId): int
    {
        $query = $this->database->prepare("SELECT COUNT(ttp.np_communication_id)
            FROM   trophy_title_player ttp
            WHERE  ttp.account_id = :account_id AND ttp.np_communication_id LIKE 'N%'");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    /**
     * @param list<string> $scannedGames
     */
    public function shouldDeleteMissingZeroPercentGames(int $psnGameCount, int $ourGameCount, array $scannedGames): bool
    {
        if ($psnGameCount <= 0 || $scannedGames === []) {
            return false;
        }

        return $psnGameCount !== $ourGameCount;
    }

    public function shouldSuppressDeletionForIncompleteScan(
        bool $shouldDeleteMissingGames,
        int $gameCountDelta,
        bool $scanCompletedCleanly,
    ): bool {
        return $shouldDeleteMissingGames
            && $gameCountDelta <= -50
            && !$scanCompletedCleanly;
    }

    public function shouldRetryWhenSonyReturnsNoGames(int $psnGameCount, int $ourGameCount): bool
    {
        return $psnGameCount === 0 && $ourGameCount > 0;
    }

    /**
     * @param list<string> $scannedGames
     */
    public function deleteMissingZeroPercentGames(int $accountId, array $scannedGames): void
    {
        $query = $this->database->prepare("SELECT ttp.np_communication_id
            FROM   trophy_title_player ttp
            WHERE  ttp.account_id = :account_id AND ttp.np_communication_id LIKE 'N%'");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();
        $playerGames = $query->fetchAll();

        foreach ($playerGames as $playerGame) {
            $game = $playerGame['np_communication_id'];
            if (in_array($game, $scannedGames, true)) {
                continue;
            }

            $this->deleteMergedParentIfNoStackRemains($accountId, $game);
            $this->deletePlayerGameProgress($accountId, $game);
        }
    }

    private function deleteMergedParentIfNoStackRemains(int $accountId, string $game): void
    {
        $query = $this->database->prepare("SELECT ttm.parent_np_communication_id
            FROM   trophy_title_meta ttm
            WHERE  ttm.np_communication_id = :np_communication_id");
        $query->bindValue(':np_communication_id', $game, PDO::PARAM_STR);
        $query->execute();
        $mergedGame = $query->fetchColumn();
        if (!$mergedGame) {
            return;
        }

        $query = $this->database->prepare("SELECT ttm.np_communication_id
            FROM   trophy_title_meta ttm
            WHERE  ttm.parent_np_communication_id = :parent_np_communication_id AND ttm.np_communication_id != :np_communication_id");
        $query->bindValue(':parent_np_communication_id', $mergedGame, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $game, PDO::PARAM_STR);
        $query->execute();
        $stackedGames = $query->fetchAll();

        foreach ($stackedGames as $stackedGame) {
            $stackedGameId = $stackedGame['np_communication_id'];

            $query = $this->database->prepare("SELECT ttp.np_communication_id
                FROM   trophy_title_player ttp
                WHERE  ttp.account_id = :account_id AND ttp.np_communication_id = :np_communication_id");
            $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $query->bindValue(':np_communication_id', $stackedGameId, PDO::PARAM_STR);
            $query->execute();

            if ($query->fetchColumn()) {
                return;
            }
        }

        $this->deletePlayerGameProgress($accountId, (string) $mergedGame);
    }

    private function deletePlayerGameProgress(int $accountId, string $npCommunicationId): void
    {
        $query = $this->database->prepare("DELETE FROM trophy_group_player tgp WHERE tgp.account_id = :account_id AND tgp.np_communication_id = :np_communication_id");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $query = $this->database->prepare("DELETE FROM trophy_title_player ttp WHERE ttp.account_id = :account_id AND ttp.np_communication_id = :np_communication_id");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $query = $this->database->prepare("DELETE FROM trophy_earned te WHERE te.account_id = :account_id AND te.np_communication_id = :np_communication_id");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }
}
