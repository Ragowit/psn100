<?php

declare(strict_types=1);

require_once __DIR__ . '/GameAvailabilityStatus.php';

class GameStatusService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function updateGameStatus(int $gameId, GameAvailabilityStatus $status): string
    {
        if ($gameId < 0) {
            throw new InvalidArgumentException('Game ID must be a non-negative integer.');
        }

        $this->database->beginTransaction();

        try {
            $this->updateStatus($gameId, $status);
            $this->logStatusChange($gameId, $status->changeType());
            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();

            throw $exception;
        }

        return $status->statusText();
    }

    private function updateStatus(int $gameId, GameAvailabilityStatus $status): void
    {
        $npCommunicationIdQuery = $this->database->prepare(
            'SELECT np_communication_id FROM trophy_title WHERE id = :game_id'
        );
        $npCommunicationIdQuery->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $npCommunicationIdQuery->execute();

        $npCommunicationId = $npCommunicationIdQuery->fetchColumn();

        if ($npCommunicationId === false) {
            return;
        }

        $query = $this->database->prepare(
            'UPDATE trophy_title_meta SET status = :status WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':status', $status->value, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
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
}
