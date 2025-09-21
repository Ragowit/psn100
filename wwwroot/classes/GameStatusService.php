<?php

declare(strict_types=1);

class GameStatusService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function updateGameStatus(int $gameId, int $status): string
    {
        if ($gameId < 0) {
            throw new InvalidArgumentException('Game ID must be a non-negative integer.');
        }

        $statusDetails = $this->getStatusDetails($status);

        $this->database->beginTransaction();

        try {
            $this->updateStatus($gameId, $status);
            $this->logStatusChange($gameId, $statusDetails['changeType']);
            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();

            throw $exception;
        }

        return $statusDetails['statusText'];
    }

    private function updateStatus(int $gameId, int $status): void
    {
        $query = $this->database->prepare(
            'UPDATE trophy_title SET status = :status WHERE id = :game_id'
        );
        $query->bindValue(':status', $status, PDO::PARAM_INT);
        $query->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $query->execute();
    }

    private function logStatusChange(int $gameId, string $changeType): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES (:change_type, :param_1)"
        );
        $query->bindValue(':change_type', $changeType, PDO::PARAM_STR);
        $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * @return array{changeType: string, statusText: string}
     */
    private function getStatusDetails(int $status): array
    {
        return match ($status) {
            1 => ['changeType' => 'GAME_DELISTED', 'statusText' => 'delisted'],
            3 => ['changeType' => 'GAME_OBSOLETE', 'statusText' => 'obsolete'],
            4 => ['changeType' => 'GAME_DELISTED_AND_OBSOLETE', 'statusText' => 'delisted & obsolete'],
            default => ['changeType' => 'GAME_NORMAL', 'statusText' => 'normal'],
        };
    }
}
