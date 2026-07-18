<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';

/**
 * Persists trophy catalog rows shared between player scans and admin rescans.
 */
final class TrophyCatalogSynchronizer
{
    public function __construct(
        private readonly PDO $database,
    ) {
    }

    /**
     * @return array{detail: ?string, icon: ?string, platform: string, platforms: array<int, string>, set_version: ?string}
     */
    public function fetchExistingTrophyTitleInfo(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT detail, icon_url, platform, set_version FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return [
                'detail' => null,
                'icon' => null,
                'platform' => '',
                'platforms' => [],
                'set_version' => null,
            ];
        }

        $platform = isset($row['platform']) ? (string) $row['platform'] : '';
        $platforms = CommaSeparatedValues::parseTrimmed($platform);

        return [
            'detail' => self::toNullableString($row['detail'] ?? null),
            'icon' => self::toNullableString($row['icon_url'] ?? null),
            'platform' => $platform,
            'platforms' => $platforms,
            'set_version' => self::toNullableString($row['set_version'] ?? null),
        ];
    }

    /**
     * @return array{name: ?string, detail: ?string, icon_url: ?string, set_version: ?string}|null
     */
    public function fetchExistingTrophyTitleRow(string $npCommunicationId): ?array
    {
        $query = $this->database->prepare(
            'SELECT name, detail, icon_url, platform, set_version
            FROM trophy_title
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, array{name: ?string, detail: ?string, icon: ?string}>
     */
    public function fetchExistingTrophyGroupData(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT group_id, name, detail, icon_url FROM trophy_group WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $groups = [];
        while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
            $groupId = (string) $row['group_id'];
            $groups[$groupId] = [
                'name' => self::toNullableString($row['name'] ?? null),
                'detail' => self::toNullableString($row['detail'] ?? null),
                'icon' => self::toNullableString($row['icon_url'] ?? null),
            ];
        }

        return $groups;
    }

    /**
     * @return array{name: ?string, detail: ?string, icon_url: ?string}|null
     */
    public function fetchExistingTrophyGroup(string $npCommunicationId, string $groupId): ?array
    {
        $query = $this->database->prepare(
            'SELECT name, detail, icon_url
            FROM trophy_group
            WHERE np_communication_id = :np_communication_id AND group_id = :group_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, array<int, array<string, ?string>>>
     */
    public function fetchExistingTrophyData(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT group_id, order_id, hidden, type, name, detail, icon_url, progress_target_value, reward_name, reward_image_url'
            . ' FROM trophy WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $trophies = [];
        while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
            $groupId = (string) $row['group_id'];
            $orderId = (int) $row['order_id'];
            $trophies[$groupId][$orderId] = [
                'hidden' => self::toNullableString($row['hidden'] ?? null),
                'type' => self::toNullableString($row['type'] ?? null),
                'name' => self::toNullableString($row['name'] ?? null),
                'detail' => self::toNullableString($row['detail'] ?? null),
                'icon' => self::toNullableString($row['icon_url'] ?? null),
                'progress_target_value' => self::toNullableString($row['progress_target_value'] ?? null),
                'reward_name' => self::toNullableString($row['reward_name'] ?? null),
                'reward_image' => self::toNullableString($row['reward_image_url'] ?? null),
            ];
        }

        return $trophies;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchExistingTrophy(string $npCommunicationId, string $groupId, int $orderId): ?array
    {
        $query = $this->database->prepare(
            'SELECT hidden, type, name, detail, icon_url, progress_target_value, reward_name, reward_image_url
            FROM trophy
            WHERE np_communication_id = :np_communication_id
            AND group_id = :group_id
            AND order_id = :order_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function upsertTrophyTitle(
        string $npCommunicationId,
        string $name,
        string $detail,
        string $iconFilename,
        string $platform,
        string $setVersion,
        bool $incomingVersionIsOlderThanStored,
    ): int {
        $query = $this->database->prepare("INSERT INTO trophy_title(
                np_communication_id,
                name,
                detail,
                icon_url,
                platform,
                set_version
            )
            VALUES(
                :np_communication_id,
                :name,
                :detail,
                :icon_url,
                :platform,
                :set_version
            ) AS new
            ON DUPLICATE KEY
            UPDATE
                detail = CASE
                    WHEN :incoming_version_is_older = 1 THEN trophy_title.detail
                    ELSE new.detail
                END,
                icon_url = new.icon_url,
                set_version = CASE
                    WHEN trophy_title.set_version IS NULL OR TRIM(trophy_title.set_version) = '' THEN new.set_version
                    WHEN CAST(SUBSTRING_INDEX(TRIM(new.set_version), '.', 1) AS UNSIGNED)
                        > CAST(SUBSTRING_INDEX(TRIM(trophy_title.set_version), '.', 1) AS UNSIGNED) THEN new.set_version
                    WHEN CAST(SUBSTRING_INDEX(TRIM(new.set_version), '.', 1) AS UNSIGNED)
                        = CAST(SUBSTRING_INDEX(TRIM(trophy_title.set_version), '.', 1) AS UNSIGNED)
                        AND CAST(SUBSTRING_INDEX(TRIM(new.set_version), '.', -1) AS UNSIGNED)
                            >= CAST(SUBSTRING_INDEX(TRIM(trophy_title.set_version), '.', -1) AS UNSIGNED) THEN new.set_version
                    ELSE trophy_title.set_version
                END");
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':name', $name, PDO::PARAM_STR);
        $query->bindValue(':detail', $detail, PDO::PARAM_STR);
        $query->bindValue(':icon_url', $iconFilename, PDO::PARAM_STR);
        $query->bindValue(':platform', $platform, PDO::PARAM_STR);
        $query->bindValue(':set_version', $setVersion, PDO::PARAM_STR);
        $query->bindValue(':incoming_version_is_older', $incomingVersionIsOlderThanStored ? 1 : 0, PDO::PARAM_INT);
        $query->execute();

        return (int) $query->rowCount();
    }

    public function upsertTrophyGroup(
        string $npCommunicationId,
        string $groupId,
        string $name,
        string $detail,
        string $iconFilename,
        bool $updateNameOnDuplicate = true,
    ): int {
        $duplicateUpdate = $updateNameOnDuplicate
            ? 'name = new.name, detail = new.detail, icon_url = new.icon_url'
            : 'detail = new.detail, icon_url = new.icon_url';

        $query = $this->database->prepare(
            "INSERT INTO trophy_group (
                np_communication_id,
                group_id,
                name,
                detail,
                icon_url
            )
            VALUES (
                :np_communication_id,
                :group_id,
                :name,
                :detail,
                :icon_url
            ) AS new
            ON DUPLICATE KEY UPDATE
                {$duplicateUpdate}"
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':name', $name, PDO::PARAM_STR);
        $query->bindValue(':detail', $detail, PDO::PARAM_STR);
        $query->bindValue(':icon_url', $iconFilename, PDO::PARAM_STR);
        $query->execute();

        return (int) $query->rowCount();
    }

    public function upsertTrophy(
        string $npCommunicationId,
        string $groupId,
        int $orderId,
        int $hidden,
        string $type,
        string $name,
        string $detail,
        string $iconFilename,
        ?int $progressTargetValue,
        ?string $rewardName,
        ?string $rewardImageFilename,
    ): int {
        $query = $this->database->prepare(
            'INSERT INTO trophy (
                np_communication_id,
                group_id,
                order_id,
                hidden,
                type,
                name,
                detail,
                icon_url,
                progress_target_value,
                reward_name,
                reward_image_url
            )
            VALUES (
                :np_communication_id,
                :group_id,
                :order_id,
                :hidden,
                :type,
                :name,
                :detail,
                :icon_url,
                :progress_target_value,
                :reward_name,
                :reward_image_url
            ) AS new
            ON DUPLICATE KEY UPDATE
                hidden = new.hidden,
                type = new.type,
                name = new.name,
                detail = new.detail,
                icon_url = new.icon_url,
                progress_target_value = new.progress_target_value,
                reward_name = new.reward_name,
                reward_image_url = new.reward_image_url'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $query->bindValue(':hidden', $hidden, PDO::PARAM_INT);
        $query->bindValue(':type', $type, PDO::PARAM_STR);
        $query->bindValue(':name', $name, PDO::PARAM_STR);
        $query->bindValue(':detail', $detail, PDO::PARAM_STR);
        $query->bindValue(':icon_url', $iconFilename, PDO::PARAM_STR);

        if ($progressTargetValue === null) {
            $query->bindValue(':progress_target_value', null, PDO::PARAM_NULL);
        } else {
            $query->bindValue(':progress_target_value', $progressTargetValue, PDO::PARAM_INT);
        }

        $this->bindNullable($query, ':reward_name', $rewardName);
        $this->bindNullable($query, ':reward_image_url', $rewardImageFilename);

        $query->execute();

        return (int) $query->rowCount();
    }

    private function bindNullable(PDOStatement $query, string $parameter, ?string $value): void
    {
        if ($value === null) {
            $query->bindValue($parameter, null, PDO::PARAM_NULL);

            return;
        }

        $query->bindValue($parameter, $value, PDO::PARAM_STR);
    }

    private static function toNullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
