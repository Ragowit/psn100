<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';
require_once __DIR__ . '/ChangelogPaginator.php';

final class ChangelogService
{
    public const PAGE_SIZE = 50;
    private const FILTERED_CHANGE_TYPES = "('GAME_RESCAN', 'GAME_VERSION')";

    private ?int $cachedTotalChangeCount = null;

    public function __construct(private readonly PDO $database)
    {
    }

    public function getTotalChangeCount(): int
    {
        if ($this->cachedTotalChangeCount !== null) {
            return $this->cachedTotalChangeCount;
        }

        $query = $this->database->prepare(
            sprintf('SELECT COUNT(*) FROM psn100_change WHERE change_type NOT IN %s', self::FILTERED_CHANGE_TYPES)
        );
        $query->execute();

        $this->cachedTotalChangeCount = (int) ($query->fetchColumn() ?: 0);

        return $this->cachedTotalChangeCount;
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
                COUNT(*) OVER() AS total_rows,
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
            WHERE c.change_type NOT IN ('GAME_RESCAN', 'GAME_VERSION')
            ORDER BY c.time DESC
            LIMIT :limit OFFSET :offset
            SQL
        );
        $query->bindValue(':offset', $paginator->getOffset(), PDO::PARAM_INT);
        $query->bindValue(':limit', $paginator->getLimit(), PDO::PARAM_INT);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        if ($rows !== [] && is_numeric($rows[0]['total_rows'] ?? null)) {
            $this->cachedTotalChangeCount = max(0, (int) $rows[0]['total_rows']);
        }

        return array_map(
            static fn(array $row): ChangelogEntry => ChangelogEntry::fromArray($row),
            $rows
        );
    }
}
