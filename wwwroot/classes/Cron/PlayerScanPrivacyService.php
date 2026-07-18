<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerStatus.php';
require_once __DIR__ . '/PlayerScanTrophySummaryAccessResult.php';

/**
 * Marks players as private and resolves trophy-summary access during scans.
 *
 * Encapsulates private-profile detection and persistence that were previously
 * embedded in ThirtyMinuteCronJob and PlayerScanProfileSynchronizer.
 */
final class PlayerScanPrivacyService
{
    private const \Closure DEFAULT_SLEEPER = sleep(...);

    private readonly \Closure $sleeper;

    public function __construct(
        private readonly PDO $database,
        private readonly WorkerScanCoordinator $workerScanCoordinator,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = \Closure::fromCallable($sleeper ?? self::DEFAULT_SLEEPER);
    }

    public function markAsPrivateByOnlineId(string $onlineId): void
    {
        $privateStatus = PlayerStatus::PRIVATE_PROFILE->value;

        $query = $this->database->prepare(
            'UPDATE player
            SET `status` = :status, last_updated_date = NOW()
            WHERE online_id = :online_id AND `status` != :flagged_status'
        );
        $query->bindValue(':status', $privateStatus, PDO::PARAM_INT);
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->bindValue(':flagged_status', PlayerStatus::FLAGGED->value, PDO::PARAM_INT);
        $query->execute();

        $query = $this->database->prepare('DELETE FROM player_queue WHERE online_id = :online_id');
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();
    }

    public function markAsPrivateByAccountId(string $accountId, string $queueOnlineId): void
    {
        $privateStatus = PlayerStatus::PRIVATE_PROFILE->value;

        $query = $this->database->prepare(
            'UPDATE player
            SET `status` = :status, last_updated_date = NOW()
            WHERE account_id = :account_id AND `status` != :flagged_status'
        );
        $query->bindValue(':status', $privateStatus, PDO::PARAM_INT);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->bindValue(':flagged_status', PlayerStatus::FLAGGED->value, PDO::PARAM_INT);
        $query->execute();

        $query = $this->database->prepare('DELETE FROM player_queue WHERE online_id = :online_id');
        $query->bindValue(':online_id', $queueOnlineId, PDO::PARAM_STR);
        $query->execute();
    }

    public function resolveTrophySummaryLevel(object $user, int $workerId): PlayerScanTrophySummaryAccessResult
    {
        try {
            return PlayerScanTrophySummaryAccessResult::accessible((int) $user->trophySummary()->level());
        } catch (TypeError) {
            $this->pauseBeforeRetryingScan($workerId, 'Encountered a problem while scanning. Waiting 1 minute before retrying.');

            return PlayerScanTrophySummaryAccessResult::abortScan();
        } catch (Exception) {
            $this->workerScanCoordinator->setWaitingScanProgress(
                $workerId,
                'Profile scan failed, waiting 1 minute before confirming privacy.'
            );
            ($this->sleeper)(60);

            try {
                return PlayerScanTrophySummaryAccessResult::accessible((int) $user->trophySummary()->level());
            } catch (TypeError) {
                $this->pauseBeforeRetryingScan($workerId, 'Encountered a problem while scanning. Waiting 1 minute before retrying.');

                return PlayerScanTrophySummaryAccessResult::abortScan();
            } catch (Exception) {
                $this->markAsPrivateByAccountId((string) $user->accountId(), (string) $user->onlineId());

                return PlayerScanTrophySummaryAccessResult::privateProfile();
            }
        }
    }

    private function pauseBeforeRetryingScan(int $workerId, string $message): void
    {
        $this->workerScanCoordinator->setWaitingScanProgress($workerId, $message);
        ($this->sleeper)(60);
    }
}
