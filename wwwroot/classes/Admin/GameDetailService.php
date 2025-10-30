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
                tt.np_communication_id,
                tt.name,
                tt.icon_url,
                tt.platform,
                ttm.message,
                tt.set_version,
                ttm.region,
                ttm.psnprofiles_id
            FROM
                trophy_title tt
                LEFT JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                tt.id = :game_id'
        );
        $query->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return GameDetail::fromArray($gameId, $row);
    }

    public function getGameDetailByNpCommunicationId(string $npCommunicationId): ?GameDetail
    {
        $query = $this->database->prepare(
            'SELECT
                tt.id,
                tt.np_communication_id,
                tt.name,
                tt.icon_url,
                tt.platform,
                ttm.message,
                tt.set_version,
                ttm.region,
                ttm.psnprofiles_id
            FROM
                trophy_title tt
                LEFT JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                tt.np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $gameId = (int) ($row['id'] ?? 0);

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
                    set_version = :set_version
                WHERE
                    id = :game_id'
            );
            $query->bindValue(':name', $gameDetail->getName(), PDO::PARAM_STR);
            $query->bindValue(':icon_url', $gameDetail->getIconUrl(), PDO::PARAM_STR);
            $query->bindValue(':platform', $gameDetail->getPlatform(), PDO::PARAM_STR);
            $query->bindValue(':set_version', $gameDetail->getSetVersion(), PDO::PARAM_STR);
            $query->bindValue(':game_id', $gameDetail->getId(), PDO::PARAM_INT);
            $query->execute();

            $npCommunicationId = $gameDetail->getNpCommunicationId();
            if ($npCommunicationId === null || $npCommunicationId === '') {
                $lookup = $this->database->prepare(
                    'SELECT np_communication_id FROM trophy_title WHERE id = :game_id'
                );
                $lookup->bindValue(':game_id', $gameDetail->getId(), PDO::PARAM_INT);
                $lookup->execute();

                $npCommunicationId = $lookup->fetchColumn();
                if ($npCommunicationId === false || $npCommunicationId === null || $npCommunicationId === '') {
                    throw new RuntimeException('Unable to determine NP communication ID for game update.');
                }

                $npCommunicationId = (string) $npCommunicationId;
            }

            $updateMetaQuery = $this->database->prepare(
                'UPDATE
                    trophy_title_meta
                SET
                    message = :message,
                    region = :region,
                    psnprofiles_id = :psnprofiles_id
                WHERE
                    np_communication_id = :np_communication_id'
            );
            $this->bindMetaParameters($updateMetaQuery, $gameDetail, $npCommunicationId);
            $updateMetaQuery->execute();

            if ($updateMetaQuery->rowCount() === 0) {
                $insertMetaQuery = $this->database->prepare(
                    'INSERT INTO
                        trophy_title_meta (np_communication_id, message, region, psnprofiles_id)
                    VALUES
                        (:np_communication_id, :message, :region, :psnprofiles_id)'
                );
                $this->bindMetaParameters($insertMetaQuery, $gameDetail, $npCommunicationId);
                $insertMetaQuery->execute();
            }

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

    private function bindMetaParameters(PDOStatement $statement, GameDetail $gameDetail, string $npCommunicationId): void
    {
        $statement->bindValue(':message', $gameDetail->getMessage(), PDO::PARAM_STR);

        $region = $gameDetail->getRegion();
        if ($region === null) {
            $statement->bindValue(':region', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':region', $region, PDO::PARAM_STR);
        }

        $psnprofilesId = $gameDetail->getPsnprofilesId();
        if ($psnprofilesId === null) {
            $statement->bindValue(':psnprofiles_id', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':psnprofiles_id', $psnprofilesId, PDO::PARAM_STR);
        }

        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
    }
}
