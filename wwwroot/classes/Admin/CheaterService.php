<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerStatus.php';

final class CheaterService
{
    public function __construct(
        private readonly PDO $database,
    ) {
    }

    public function markPlayerAsCheater(string $onlineId): void
    {
        if ($onlineId === '') {
            throw new InvalidArgumentException('Online ID cannot be empty.');
        }

        $this->database->beginTransaction();

        try {
            $flaggedStatus = PlayerStatus::FLAGGED->value;

            $query = $this->database->prepare(
                "UPDATE player
                SET `status` = {$flaggedStatus},
                    rank_last_week = 0,
                    rarity_rank_last_week = 0,
                    in_game_rarity_rank_last_week = 0,
                    rank_country_last_week = 0,
                    rarity_rank_country_last_week = 0,
                    in_game_rarity_rank_country_last_week = 0
                WHERE online_id = :online_id"
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
