<?php

declare(strict_types=1);

class TrophyMetaRepository
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function ensureExists(string $npCommunicationId, string $groupId, int $orderId): void
    {
        $trophyIdQuery = $this->database->prepare(
            'SELECT id FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id'
        );
        $trophyIdQuery->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $trophyIdQuery->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $trophyIdQuery->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $trophyIdQuery->execute();

        $trophyId = $trophyIdQuery->fetchColumn();

        if ($trophyId === false) {
            return;
        }

        $trophyId = (int) $trophyId;

        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);

        $insertQuery = match ($driver) {
            'sqlite' => <<<'SQL'
                INSERT OR IGNORE INTO trophy_meta (
                    trophy_id,
                    rarity_percent,
                    rarity_point,
                    status,
                    owners,
                    rarity_name
                ) VALUES (
                    :trophy_id,
                    0,
                    0,
                    0,
                    0,
                    'NONE'
                )
            SQL,
            'mysql' => <<<'SQL'
                INSERT IGNORE INTO trophy_meta (
                    trophy_id,
                    rarity_percent,
                    rarity_point,
                    status,
                    owners,
                    rarity_name
                ) VALUES (
                    :trophy_id,
                    0,
                    0,
                    0,
                    0,
                    'NONE'
                )
            SQL,
            default => throw new RuntimeException("Unsupported PDO driver: {$driver}"),
        };

        $insertMeta = $this->database->prepare($insertQuery);
        $insertMeta->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $insertMeta->execute();
    }
}
