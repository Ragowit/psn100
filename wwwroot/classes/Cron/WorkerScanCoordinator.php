<?php

declare(strict_types=1);

/**
 * Manages worker scan progress and player queue reservation for the 30-minute cron.
 *
 * Encapsulates setting table updates that were previously embedded in
 * ThirtyMinuteCronJob so the main scan loop can focus on PSN API orchestration.
 */
final class WorkerScanCoordinator
{
    public function __construct(
        private readonly PDO $database,
        private readonly ?\Closure $sleeper = null,
    ) {
    }

    public function setWaitingScanProgress(int $workerId, string $message): void
    {
        $this->setWorkerScanProgress(
            $workerId,
            [
                'title' => $message,
            ]
        );
    }

    /**
     * @param array{current?: int, total?: int, title?: string, npCommunicationId?: string}|null $progress
     */
    public function setWorkerScanProgress(int $workerId, ?array $progress): void
    {
        $query = $this->database->prepare(
            'UPDATE setting SET scan_progress = :scan_progress WHERE id = :worker_id'
        );

        if ($progress === null) {
            $query->bindValue(':scan_progress', null, PDO::PARAM_NULL);
        } else {
            try {
                $encodedProgress = json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $query->bindValue(':scan_progress', $encodedProgress, PDO::PARAM_STR);
            } catch (JsonException) {
                $query->bindValue(':scan_progress', null, PDO::PARAM_NULL);
            }
        }

        $query->bindValue(':worker_id', $workerId, PDO::PARAM_INT);
        $query->execute();
    }

    public function releaseWorkerFromCurrentScan(int $workerId): void
    {
        $query = $this->database->prepare(
            'UPDATE setting SET scanning = :id, scan_progress = NULL WHERE id = :id'
        );
        $query->bindValue(':id', $workerId, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * @param array<string, mixed>|false $player
     * @return array<string, mixed>|null
     */
    public function reservePlayerForScanning(int $workerId, array|false $player): ?array
    {
        if ($player === false) {
            $this->releaseWorkerFromCurrentScan($workerId);
            $this->setWaitingScanProgress(
                $workerId,
                'No player to scan; retrying soon.'
            );
            $this->pause(5);

            return null;
        }

        $query = $this->database->prepare(
            'UPDATE setting SET scanning = :scanning, scan_progress = NULL WHERE id = :worker_id'
        );
        $query->bindValue(':scanning', $player['online_id'], PDO::PARAM_STR);
        $query->bindValue(':worker_id', $workerId, PDO::PARAM_INT);
        $query->execute();

        return $player;
    }

    /**
     * @param array<string, mixed> $player
     */
    public function deferPlayerScanAfterFailure(array $player, int $workerId): void
    {
        $query = $this->database->prepare(
            'DELETE FROM player_queue
            WHERE  online_id = :online_id '
        );
        $query->bindValue(':online_id', $player['online_id'], PDO::PARAM_STR);
        $query->execute();

        $query = $this->database->prepare(
            'SELECT account_id
            FROM   player
            WHERE  online_id = :online_id '
        );
        $query->bindValue(':online_id', $player['online_id'], PDO::PARAM_STR);
        $query->execute();
        $accountId = $query->fetchColumn();

        if ($accountId !== false) {
            $query = $this->database->prepare(
                'UPDATE player
                SET last_updated_date = CASE
                    WHEN last_updated_date IS NULL THEN NOW()
                    ELSE DATE_ADD(last_updated_date, INTERVAL 1 HOUR)
                END
                WHERE  account_id = :account_id '
            );
            $query->bindValue(':account_id', (string) $accountId, PDO::PARAM_STR);
            $query->execute();
        }

        $this->releaseWorkerFromCurrentScan($workerId);
    }

    private function pause(int $seconds): void
    {
        if ($this->sleeper !== null) {
            ($this->sleeper)($seconds);

            return;
        }

        sleep($seconds);
    }
}
