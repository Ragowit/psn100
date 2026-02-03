<?php

declare(strict_types=1);

final class GameHistoryService
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     groups: array<int, array{group_id: string, name: ?string, detail: ?string, icon_url: ?string}>,
     *     trophies: array<int, array{group_id: string, order_id: int, name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int, is_unobtainable: bool}>
     * }>
     */
    public function getHistoryForGame(int $gameId): array
    {
        try {
            $query = $this->database->prepare(
                <<<'SQL'
                SELECT
                    id,
                    detail,
                    icon_url,
                    set_version,
                    discovered_at
                FROM
                    trophy_title_history
                WHERE
                    trophy_title_id = :game_id
                ORDER BY
                    discovered_at DESC,
                    id DESC
                SQL
            );
        } catch (PDOException $exception) {
            return [];
        }

        $query->bindValue(':game_id', $gameId, PDO::PARAM_INT);

        try {
            $query->execute();
        } catch (PDOException $exception) {
            return [];
        }

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $historyIds = [];
        foreach ($rows as $row) {
            $historyIds[] = isset($row['id']) ? (int) $row['id'] : 0;
        }

        $groupChanges = $this->fetchGroupChanges($historyIds);
        $trophyChanges = $this->fetchTrophyChanges($historyIds);

        $history = [];
        foreach ($rows as $row) {
            $historyId = isset($row['id']) ? (int) $row['id'] : 0;
            $title = [
                'detail' => isset($row['detail']) ? (string) $row['detail'] : null,
                'icon_url' => isset($row['icon_url']) ? (string) $row['icon_url'] : null,
                'set_version' => isset($row['set_version']) ? (string) $row['set_version'] : null,
            ];

            if ($title['detail'] === null && $title['icon_url'] === null && $title['set_version'] === null) {
                $title = null;
            }

            $history[] = [
                'historyId' => $historyId,
                'discoveredAt' => $this->createDateTime($row['discovered_at'] ?? ''),
                'title' => $title,
                'groups' => $groupChanges[$historyId] ?? [],
                'trophies' => $trophyChanges[$historyId] ?? [],
            ];
        }

        return $history;
    }

    /**
     * @param array<int, int> $historyIds
     * @return array<int, array<int, array{group_id: string, name: ?string, detail: ?string, icon_url: ?string}>>
     */
    private function fetchGroupChanges(array $historyIds): array
    {
        $rows = $this->fetchRows(
            <<<'SQL'
            SELECT
                title_history_id,
                group_id,
                name,
                detail,
                icon_url
            FROM
                trophy_group_history
            WHERE
                title_history_id IN (%s)
            ORDER BY
                CASE WHEN group_id = 'default' THEN 0 ELSE 1 END,
                CASE WHEN group_id = 'default' THEN 0 ELSE group_id + 0 END,
                group_id
            SQL,
            $historyIds
        );

        $result = [];
        foreach ($rows as $row) {
            $historyId = isset($row['title_history_id']) ? (int) $row['title_history_id'] : 0;
            $result[$historyId] ??= [];
            $result[$historyId][] = [
                'group_id' => isset($row['group_id']) ? (string) $row['group_id'] : '',
                'name' => isset($row['name']) ? (string) $row['name'] : null,
                'detail' => isset($row['detail']) ? (string) $row['detail'] : null,
                'icon_url' => isset($row['icon_url']) ? (string) $row['icon_url'] : null,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int> $historyIds
     * @return array<int, array<int, array{group_id: string, order_id: int, name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int, is_unobtainable: bool}>>
    */
    private function fetchTrophyChanges(array $historyIds): array
    {
        $rows = $this->fetchRows(
            <<<'SQL'
            SELECT
                th.title_history_id,
                th.group_id,
                th.order_id,
                th.name,
                th.detail,
                th.icon_url,
                th.progress_target_value,
                tm.status AS trophy_status
            FROM
                trophy_history th
                JOIN trophy_title_history tth ON tth.id = th.title_history_id
                JOIN trophy_title tt ON tt.id = tth.trophy_title_id
                LEFT JOIN trophy t ON t.np_communication_id = tt.np_communication_id
                    AND t.group_id = th.group_id
                    AND t.order_id = th.order_id
                LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE
                th.title_history_id IN (%s)
            ORDER BY
                CASE WHEN th.group_id = 'default' THEN 0 ELSE 1 END,
                CASE WHEN th.group_id = 'default' THEN 0 ELSE th.group_id + 0 END,
                th.order_id
            SQL,
            $historyIds
        );

        $result = [];
        foreach ($rows as $row) {
            $historyId = isset($row['title_history_id']) ? (int) $row['title_history_id'] : 0;
            $result[$historyId] ??= [];
            $result[$historyId][] = [
                'group_id' => isset($row['group_id']) ? (string) $row['group_id'] : '',
                'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : 0,
                'name' => isset($row['name']) ? (string) $row['name'] : null,
                'detail' => isset($row['detail']) ? (string) $row['detail'] : null,
                'icon_url' => isset($row['icon_url']) ? (string) $row['icon_url'] : null,
                'progress_target_value' => isset($row['progress_target_value'])
                    ? ($row['progress_target_value'] === null ? null : (int) $row['progress_target_value'])
                    : null,
                'is_unobtainable' => isset($row['trophy_status']) ? ((int) $row['trophy_status'] === 1) : false,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int> $historyIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $sqlTemplate, array $historyIds): array
    {
        if ($historyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($historyIds), '?'));
        $sql = sprintf($sqlTemplate, $placeholders);

        try {
            $query = $this->database->prepare($sql);
        } catch (PDOException $exception) {
            return [];
        }

        foreach ($historyIds as $index => $historyId) {
            $query->bindValue($index + 1, $historyId, PDO::PARAM_INT);
        }

        try {
            $query->execute();
        } catch (PDOException $exception) {
            return [];
        }

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function createDateTime(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return new DateTimeImmutable('@0');
        }
    }
}
