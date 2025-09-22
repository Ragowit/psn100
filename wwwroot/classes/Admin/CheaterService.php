<?php

declare(strict_types=1);

class CheaterService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function markPlayerAsCheater(string $onlineId): void
    {
        if ($onlineId === '') {
            throw new InvalidArgumentException('Online ID cannot be empty.');
        }

        $this->database->beginTransaction();

        try {
            $query = $this->database->prepare(
                'UPDATE player
                SET `status` = 1,
                    rank_last_week = 0,
                    rarity_rank_last_week = 0,
                    rank_country_last_week = 0,
                    rarity_rank_country_last_week = 0
                WHERE online_id = :online_id'
            );
            $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
            $query->execute();

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }
}
