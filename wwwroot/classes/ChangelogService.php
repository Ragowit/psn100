<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';
require_once __DIR__ . '/ChangelogPaginator.php';

class ChangelogService
{
    public const PAGE_SIZE = 50;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getTotalChangeCount(): int
    {
        $query = $this->database->prepare('SELECT COUNT(*) FROM psn100_change');
        $query->execute();

        $count = $query->fetchColumn();

        if ($count === false) {
            return 0;
        }

        return (int) $count;
    }

    /**
     * @return array<int, ChangelogEntry>
     */
    public function getChanges(ChangelogPaginator $paginator): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                c.*,
                tt1.name AS param_1_name,
                tt1.platform AS param_1_platform,
                ttm1.region AS param_1_region,
                tt2.name AS param_2_name,
                tt2.platform AS param_2_platform,
                ttm2.region AS param_2_region
            FROM psn100_change c
            LEFT JOIN trophy_title tt1 ON tt1.id = c.param_1
            LEFT JOIN trophy_title_meta ttm1 ON ttm1.np_communication_id = tt1.np_communication_id
            LEFT JOIN trophy_title tt2 ON tt2.id = c.param_2
            LEFT JOIN trophy_title_meta ttm2 ON ttm2.np_communication_id = tt2.np_communication_id
            ORDER BY c.time DESC
            LIMIT :offset, :limit
            SQL
        );
        $query->bindValue(':offset', $paginator->getOffset(), PDO::PARAM_INT);
        $query->bindValue(':limit', $paginator->getLimit(), PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = ChangelogEntry::fromArray($row);
        }

        return $entries;
    }
}
