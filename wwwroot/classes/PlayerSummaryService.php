<?php

declare(strict_types=1);

class PlayerSummaryService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getSummary(int $accountId): PlayerSummary
    {
        $numberOfGames = $this->fetchNumberOfGames($accountId);
        $numberOfCompletedGames = $this->fetchNumberOfCompletedGames($accountId);
        $averageProgress = $this->fetchAverageProgress($accountId);
        $unearnedTrophies = $this->fetchUnearnedTrophies($accountId);

        return new PlayerSummary($numberOfGames, $numberOfCompletedGames, $averageProgress, $unearnedTrophies);
    }

    private function fetchNumberOfGames(int $accountId): int
    {
        $query = $this->database->prepare(
            'SELECT COUNT(*) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchColumn();

        return $this->toInt($result);
    }

    private function fetchNumberOfCompletedGames(int $accountId): int
    {
        $query = $this->database->prepare(
            'SELECT COUNT(*) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.progress = 100 AND ttp.account_id = :account_id'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchColumn();

        return $this->toInt($result);
    }

    private function fetchAverageProgress(int $accountId): ?float
    {
        $query = $this->database->prepare(
            'SELECT ROUND(AVG(ttp.progress), 2) FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchColumn();

        if ($result === false || $result === null) {
            return null;
        }

        return (float) $result;
    }

    private function fetchUnearnedTrophies(int $accountId): int
    {
        $query = $this->database->prepare(
            'SELECT SUM(tt.bronze - ttp.bronze + tt.silver - ttp.silver + tt.gold - ttp.gold + tt.platinum - ttp.platinum) FROM trophy_title_player ttp JOIN trophy_title tt USING(np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchColumn();

        return $this->toInt($result);
    }

    private function toInt(mixed $value): int
    {
        if ($value === false || $value === null) {
            return 0;
        }

        return (int) $value;
    }
}
