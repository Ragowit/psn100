<?php

declare(strict_types=1);

class TrophyMetaRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function ensureExists(string $npCommunicationId, string $groupId, int $orderId): void
    {
        $insertMeta = $this->database->prepare(
            <<<'SQL'
                INSERT IGNORE INTO trophy_meta (
                    trophy_id,
                    rarity_percent,
                    rarity_point,
                    status,
                    owners,
                    rarity_name
                )
                SELECT
                    trophy.id,
                    0,
                    0,
                    0,
                    0,
                    'NONE'
                FROM trophy
                WHERE trophy.np_communication_id = :np_communication_id
                  AND trophy.group_id = :group_id
                  AND trophy.order_id = :order_id
            SQL
        );

        $insertMeta->execute([
            ':np_communication_id' => $npCommunicationId,
            ':group_id' => $groupId,
            ':order_id' => $orderId,
        ]);
    }
}
