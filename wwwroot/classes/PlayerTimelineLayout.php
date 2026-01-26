<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerTimelineEntry.php';
require_once __DIR__ . '/PlayerTimelineLayoutItem.php';

final class PlayerTimelineLayout
{
    /**
     * @param PlayerTimelineEntry[] $entries
     * @return list<list<PlayerTimelineLayoutItem>>
     */
    public static function buildRows(DateTimeImmutable $startDate, array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $rows = [];
        $rowEndDates = [];

        foreach ($entries as $entry) {
            $rowIndex = null;
            foreach ($rowEndDates as $index => $rowEndDate) {
                if ($entry->getFirstTrophyDate() > $rowEndDate) {
                    $rowIndex = $index;
                    break;
                }
            }

            if ($rowIndex === null) {
                $rowIndex = count($rows);
                $rows[$rowIndex] = [];
                $rowEndDates[$rowIndex] = $startDate->modify('-1 day');
            }

            $lastDate = $rowEndDates[$rowIndex];
            $offsetDays = max(
                0,
                (int) $lastDate->diff($entry->getFirstTrophyDate())->format('%r%a') - 1
            );
            $durationDays = (int) $entry->getFirstTrophyDate()
                ->diff($entry->getLastTrophyDate())
                ->format('%r%a') + 1;

            $rows[$rowIndex][] = new PlayerTimelineLayoutItem(
                $entry,
                $offsetDays,
                max(1, $durationDays)
            );
            $rowEndDates[$rowIndex] = $entry->getLastTrophyDate();
        }

        return array_values($rows);
    }
}
