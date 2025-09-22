<?php

declare(strict_types=1);

class GameDetailService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getGameDetail(int $gameId): ?GameDetail
    {
        $query = $this->database->prepare(
            'SELECT
                np_communication_id,
                name,
                icon_url,
                platform,
                message,
                set_version,
                region,
                psnprofiles_id
            FROM
                trophy_title
            WHERE
                id = :game_id'
        );
        $query->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return GameDetail::fromArray($gameId, $row);
    }

    public function updateGameDetail(GameDetail $gameDetail): GameDetail
    {
        $this->database->beginTransaction();

        try {
            $query = $this->database->prepare(
                'UPDATE
                    trophy_title
                SET
                    name = :name,
                    icon_url = :icon_url,
                    platform = :platform,
                    message = :message,
                    set_version = :set_version,
                    region = :region,
                    psnprofiles_id = :psnprofiles_id
                WHERE
                    id = :game_id'
            );
            $query->bindValue(':name', $gameDetail->getName(), PDO::PARAM_STR);
            $query->bindValue(':icon_url', $gameDetail->getIconUrl(), PDO::PARAM_STR);
            $query->bindValue(':platform', $gameDetail->getPlatform(), PDO::PARAM_STR);
            $query->bindValue(':message', $gameDetail->getMessage(), PDO::PARAM_STR);
            $query->bindValue(':set_version', $gameDetail->getSetVersion(), PDO::PARAM_STR);
            $query->bindValue(':region', $gameDetail->getRegion(), $gameDetail->getRegion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $query->bindValue(':psnprofiles_id', $gameDetail->getPsnprofilesId(), $gameDetail->getPsnprofilesId() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $query->bindValue(':game_id', $gameDetail->getId(), PDO::PARAM_INT);
            $query->execute();

            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }

        $this->recordChange($gameDetail->getId());

        $updatedDetail = $this->getGameDetail($gameDetail->getId());
        if ($updatedDetail === null) {
            throw new RuntimeException('Failed to load updated game details.');
        }

        return $updatedDetail;
    }

    private function recordChange(int $gameId): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_UPDATE', :param_1)"
        );
        $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
        $query->execute();
    }
}
