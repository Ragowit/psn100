<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyStatusUpdateResult.php';
require_once __DIR__ . '/TrophyStatusProgressRecalculator.php';
require_once __DIR__ . '/../TrophyMetaStatus.php';

class TrophyStatusService
{
    private readonly TrophyStatusProgressRecalculator $progressRecalculator;

    public function __construct(
        private readonly PDO $database,
        ?TrophyStatusProgressRecalculator $progressRecalculator = null,
    ) {
        $this->progressRecalculator = $progressRecalculator ?? new TrophyStatusProgressRecalculator($database);
    }

    /**
     * @param int[] $trophyIds
     */
    public function updateTrophies(array $trophyIds, int|TrophyMetaStatus $status): TrophyStatusUpdateResult
    {
        $metaStatus = TrophyMetaStatus::fromMixed($status);
        $trophyIds = $trophyIds
            |> (fn(array $ids): array => array_map(intval(...), $ids))
            |> array_unique(...)
            |> array_values(...);

        if ($trophyIds === []) {
            throw new InvalidArgumentException('No trophies were provided.');
        }

        $trophyNames = [];
        $trophyGroups = [];
        $trophyTitles = [];

        foreach ($trophyIds as $trophyId) {
            $trophy = $this->updateTrophyStatus((int) $trophyId, $metaStatus);
            $trophyNames[] = $trophy['label'];
            if (!isset($trophyGroups[$trophy['groupKey']])) {
                $trophyGroups[$trophy['groupKey']] = [
                    'np_communication_id' => $trophy['np_communication_id'],
                    'group_id' => $trophy['group_id'],
                    'trophy_ids' => [],
                ];
            }
            $trophyGroups[$trophy['groupKey']]['trophy_ids'][] = $trophy['id'];
            $trophyTitles[$trophy['np_communication_id']][] = $trophy['id'];
        }

        foreach ($trophyGroups as $group) {
            $this->progressRecalculator->recalculateGroup(
                $group['np_communication_id'],
                $group['group_id'],
                $group['trophy_ids'],
            );
        }

        foreach ($trophyTitles as $npCommunicationId => $titleTrophyIds) {
            $this->progressRecalculator->recalculateTitle((string) $npCommunicationId, $metaStatus, $titleTrophyIds);
        }

        return new TrophyStatusUpdateResult($trophyNames, $metaStatus->label());
    }

    /**
     * @return array{id: int, name: string, np_communication_id: string, group_id: string, label: string, groupKey: string}
     */
    private function updateTrophyStatus(int $trophyId, TrophyMetaStatus $status): array
    {
        try {
            $this->database->beginTransaction();

            $query = $this->database->prepare('UPDATE trophy_meta SET status = :status WHERE trophy_id = :trophy_id');
            $query->bindValue(':status', $status->value, PDO::PARAM_INT);
            $query->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
            $query->execute();

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        $query = $this->database->prepare('SELECT np_communication_id, group_id, name FROM trophy WHERE id = :trophy_id');
        $query->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $query->execute();
        $trophy = $query->fetch(PDO::FETCH_ASSOC);

        if ($trophy === false) {
            throw new RuntimeException('Trophy not found: ' . $trophyId);
        }

        $npCommunicationId = (string) $trophy['np_communication_id'];
        $groupId = (string) $trophy['group_id'];
        $name = (string) $trophy['name'];

        return [
            'id' => $trophyId,
            'name' => $name,
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'label' => $trophyId . ' (' . $name . ')',
            'groupKey' => $npCommunicationId . ',' . $groupId,
        ];
    }
}
