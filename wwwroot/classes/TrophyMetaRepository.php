<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMetaStatus.php';
require_once __DIR__ . '/TrophyRarityName.php';

final class TrophyMetaRepository
{
    private const string SELECT_TROPHY_ID_SQL = <<<'SQL'
        SELECT id
        FROM trophy
        WHERE np_communication_id = :np_communication_id
          AND group_id = :group_id
          AND order_id = :order_id
    SQL;

    private ?PDOStatement $selectTrophyIdStatement = null;
    private ?PDOStatement $insertMetaStatement = null;

    public function __construct(private readonly PDO $database)
    {
    }

    public function ensureExists(string $npCommunicationId, string $groupId, int $orderId): void
    {
        $selectTrophyId = $this->selectTrophyIdStatement ??= $this->database->prepare(self::SELECT_TROPHY_ID_SQL);
        $selectTrophyId->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $selectTrophyId->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $selectTrophyId->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $selectTrophyId->execute();

        $trophyId = $selectTrophyId->fetchColumn();

        if ($trophyId === false) {
            return;
        }

        $insertMeta = $this->insertMetaStatement ??= $this->database->prepare($this->resolveInsertSql());
        $insertMeta->bindValue(':trophy_id', (int) $trophyId, PDO::PARAM_INT);
        $insertMeta->execute();
    }

    private function resolveInsertSql(): string
    {
        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'sqlite' => $this->sqliteInsertMetaSql(),
            'mysql' => $this->mysqlInsertMetaSql(),
            default => throw new RuntimeException("Unsupported PDO driver: {$driver}"),
        };
    }

    private function sqliteInsertMetaSql(): string
    {
        $obtainableStatus = TrophyMetaStatus::Obtainable->value;
        $none = TrophyRarityName::None->toSqlLiteral();

        return <<<SQL
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
            {$obtainableStatus},
            0,
            {$none}
        )
        SQL;
    }

    private function mysqlInsertMetaSql(): string
    {
        $obtainableStatus = TrophyMetaStatus::Obtainable->value;
        $none = TrophyRarityName::None->toSqlLiteral();

        return <<<SQL
        INSERT INTO trophy_meta (
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
            {$obtainableStatus},
            0,
            {$none}
        ) AS new_meta
        ON DUPLICATE KEY UPDATE
            trophy_id = new_meta.trophy_id
        SQL;
    }
}
