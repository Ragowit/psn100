<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php';

final class DailyCronJobTest extends TestCase
{
    public function testUpdateTrophyRarityQueryUsesTopTenThousandRankingFilter(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_BATCH_QUERY');

        $this->assertStringContainsString('JOIN player_ranking pr ON pr.ranking <= 10000', $source);
        $this->assertStringContainsString('te.account_id = pr.account_id', $source);
        $this->assertStringContainsString('/ 10000.0) * 100 AS rarity_percent', $source);
        $this->assertStringContainsString('JSON_TABLE(', $source);
        $this->assertStringContainsString(':np_communication_ids', $source);
        $this->assertStringContainsString('JOIN trophy t ON t.np_communication_id = title.np_communication_id', $source);
    }

    public function testUpdateTrophyRarityQueryDrivesTrophyEarnedByAccountIdForPartitionPruning(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_BATCH_QUERY');

        $this->assertStringContainsString('ranked_owners AS', $source);
        $this->assertStringContainsString('INNER JOIN trophy_earned te', $source);
        $this->assertStringContainsString('GROUP BY te.np_communication_id, te.order_id', $source);
        $this->assertFalse(str_contains($source, 'LEFT JOIN trophy_earned te'));
    }

    public function testZeroOwnersRarityQuerySkipsTrophyEarned(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_ZERO_OWNERS_QUERY');

        $this->assertStringContainsString('UPDATE trophy_meta tm', $source);
        $this->assertStringContainsString('JOIN trophy_title_meta ttm', $source);
        $this->assertStringContainsString('IF(tm.status = 0 AND ttm.status = 0, 10000, 0)', $source);
        $this->assertStringContainsString("IF(tm.status = 0 AND ttm.status = 0, 'LEGENDARY', 'NONE')", $source);
        $this->assertStringContainsString('tm.in_game_rarity_point = 0', $source);
        $this->assertFalse(str_contains($source, 'trophy_earned'));
        $this->assertFalse(str_contains($source, 'player_ranking'));
    }

    public function testZeroOwnersFastPathUsesBatchedLiveRankedOwnerLookupNotCachedMeta(): void
    {
        $source = $this->readClassSource();

        $this->assertStringContainsString('SELECT DISTINCT ttp.np_communication_id', $source);
        $this->assertStringContainsString('FROM player_ranking pr', $source);
        $this->assertStringContainsString('JOIN trophy_title_player ttp ON ttp.account_id = pr.account_id', $source);
        $this->assertStringContainsString('WHERE pr.ranking <= 10000', $source);
        $this->assertFalse(str_contains($source, 'SELECT EXISTS ('));
        $this->assertFalse(str_contains($source, 'SELECT owners FROM trophy_title_meta'));
    }

    public function testUpdateTrophyRarityQueryAssignsRarityNamesFromThresholds(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_BATCH_QUERY');

        $this->assertStringContainsString("WHEN r.rarity_percent > 10 THEN 'COMMON'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 2 THEN 'UNCOMMON'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 0.2 THEN 'RARE'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 0.02 THEN 'EPIC'", $source);
        $this->assertStringContainsString("ELSE 'LEGENDARY'", $source);
        $this->assertStringContainsString("WHEN r.in_game_rarity_percent <= 1 THEN 'LEGENDARY'", $source);
    }

    public function testUpdateTrophyRarityQueryScopesTrophyEarnedByTitle(): void
    {
        $source = $this->readClassSource();

        $this->assertStringContainsString('SELECT np_communication_id FROM trophy_title', $source);
        $this->assertStringContainsString('fetchTopTenThousandOwnerTitleLookup', $source);
        $this->assertStringContainsString('isset($rankedOwnerTitles[$npCommunicationId])', $source);
        $this->assertStringContainsString('RANKED_OWNER_TITLE_BATCH_SIZE = 100', $source);
        $this->assertStringContainsString('updateTrophyRarityForGames', $source);
    }

    public function testUpdateTrophyTitleRarityPointsAggregatesPerGame(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TITLE_RARITY_POINTS_QUERY');

        $this->assertStringContainsString('IFNULL(SUM(tm.rarity_point), 0) AS rarity_sum', $source);
        $this->assertStringContainsString('IFNULL(SUM(tm.in_game_rarity_point), 0) AS in_game_rarity_sum', $source);
        $this->assertStringContainsString('GROUP BY t.np_communication_id', $source);
        $this->assertStringContainsString('ttm.rarity_points = r.rarity_sum', $source);
        $this->assertStringContainsString('ttm.in_game_rarity_points = r.in_game_rarity_sum', $source);
    }

    public function testExecuteWithRetryUsesInfiniteLoopWithoutMaxAttemptCap(): void
    {
        $source = $this->readClassSource();

        $this->assertStringContainsString('while (true)', $source);
        $this->assertFalse(str_contains($source, 'maxAttempt'));
        $this->assertStringContainsString('updateTrophyRarityForGames', $source);
        $this->assertStringContainsString('updateTrophyTitleRarityPoints', $source);
    }

    public function testRunRetriesPerGameRarityUpdateUntilItSucceeds(): void
    {
        $database = new DailyCronJobPerGameRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(3, $database->getPerGameUpdateCount());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
    }

    public function testRunRetriesTitleRarityPointAggregationUntilItSucceeds(): void
    {
        $database = new DailyCronJobTitleRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(0, $database->getPerGameUpdateCount());
        $this->assertSame(3, $database->getTitlePointsUpdateCount());
    }

    public function testRunRetriesRankedOwnerLookupUntilItSucceeds(): void
    {
        $database = new DailyCronJobRankedOwnerLookupRetryTestDatabase();
        $sleepCalls = [];

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(3, $database->getRankedOwnerLookupCount());
        $this->assertSame(['NPWR00001_00'], $database->getFullRarityUpdates());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
    }

    public function testRunProcessesRankedOwnerTitlesInBatchesAndZeroOwnerTitlesIndividually(): void
    {
        $database = new DailyCronJobRankedOwnerLookupTestDatabase();

        $job = new DailyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds): void {
                throw new RuntimeException('Sleeper should not be called.');
            },
        );

        $job->run();

        $this->assertSame(1, $database->getRankedOwnerLookupCount());
        $expectedFirstBatch = array_map(static fn (int $index): string => sprintf('NPWR%05d_00', $index), range(1, 100));
        $this->assertSame([$expectedFirstBatch, ['NPWR00101_00']], $database->getFullRarityUpdateBatches());
        $this->assertSame(['NPWR00102_00'], $database->getZeroOwnerRarityUpdates());
        $this->assertSame(1, $database->getTitlePointsUpdateCount());
    }

    private function readClassSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php');
        $this->assertTrue(is_string($source));

        return $source;
    }

    private function readPrivateConstant(string $name): string
    {
        $class = new ReflectionClass(DailyCronJob::class);
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_string($value));

        return $value;
    }
}

final class DailyCronJobPerGameRetryTestDatabase extends PDO
{
    private int $perGameUpdateCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getPerGameUpdateCount(): int
    {
        return $this->perGameUpdateCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'SELECT np_communication_id FROM trophy_title')) {
            return new DailyCronJobTestStatement(static function (): array {
                return ['NPWR00001_00'];
            }, isSelect: true);
        }

        if (str_contains($query, 'SELECT DISTINCT ttp.np_communication_id')) {
            // Ranked owners present => full ranked trophy_earned rarity path.
            return new DailyCronJobTestStatement(static function (): array {
                return ['NPWR00001_00'];
            }, isSelect: true);
        }

        if (str_contains($query, 'UPDATE trophy_meta tm')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->perGameUpdateCount++;

                if ($this->perGameUpdateCount < 3) {
                    throw new RuntimeException('Simulated per-game rarity update failure.');
                }
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobTitleRetryTestDatabase extends PDO
{
    private int $perGameUpdateCount = 0;

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getPerGameUpdateCount(): int
    {
        return $this->perGameUpdateCount;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'SELECT np_communication_id FROM trophy_title')) {
            return new DailyCronJobTestStatement(static function (): array {
                return [];
            }, isSelect: true);
        }

        if (str_contains($query, 'SELECT DISTINCT ttp.np_communication_id')) {
            return new DailyCronJobTestStatement(static function (): array {
                return [];
            }, isSelect: true);
        }

        if (str_contains($query, 'UPDATE trophy_meta tm')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->perGameUpdateCount++;
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;

                if ($this->titlePointsUpdateCount < 3) {
                    throw new RuntimeException('Simulated title rarity point aggregation failure.');
                }
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobRankedOwnerLookupRetryTestDatabase extends PDO
{
    private int $rankedOwnerLookupCount = 0;

    /** @var list<string> */
    private array $fullRarityUpdates = [];

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getRankedOwnerLookupCount(): int
    {
        return $this->rankedOwnerLookupCount;
    }

    /**
     * @return list<string>
     */
    public function getFullRarityUpdates(): array
    {
        return $this->fullRarityUpdates;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'SELECT np_communication_id FROM trophy_title')) {
            return new DailyCronJobTestStatement(static function (): array {
                return ['NPWR00001_00'];
            }, isSelect: true);
        }

        if (str_contains($query, 'SELECT DISTINCT ttp.np_communication_id')) {
            return new DailyCronJobTestStatement(function (): array {
                $this->rankedOwnerLookupCount++;

                if ($this->rankedOwnerLookupCount < 3) {
                    throw new RuntimeException('Simulated ranked-owner lookup failure.');
                }

                return ['NPWR00001_00'];
            }, isSelect: true);
        }

        if (str_contains($query, 'ranked_owners AS')) {
            return new DailyCronJobTestStatement(function (?array $params, array $boundValues): void {
                $json = $boundValues[':np_communication_ids'] ?? '[]';
                $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
                $this->fullRarityUpdates = array_merge($this->fullRarityUpdates, is_array($decoded) ? $decoded : []);
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobRankedOwnerLookupTestDatabase extends PDO
{
    private int $rankedOwnerLookupCount = 0;

    /** @var list<list<string>> */
    private array $fullRarityUpdateBatches = [];

    /** @var list<string> */
    private array $zeroOwnerRarityUpdates = [];

    private int $titlePointsUpdateCount = 0;

    public function __construct()
    {
    }

    public function getRankedOwnerLookupCount(): int
    {
        return $this->rankedOwnerLookupCount;
    }

    /**
     * @return list<list<string>>
     */
    public function getFullRarityUpdateBatches(): array
    {
        return $this->fullRarityUpdateBatches;
    }

    /**
     * @return list<string>
     */
    public function getZeroOwnerRarityUpdates(): array
    {
        return $this->zeroOwnerRarityUpdates;
    }

    public function getTitlePointsUpdateCount(): int
    {
        return $this->titlePointsUpdateCount;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'SELECT np_communication_id FROM trophy_title')) {
            return new DailyCronJobTestStatement(static function (): array {
                return array_map(static fn (int $index): string => sprintf('NPWR%05d_00', $index), range(1, 102));
            }, isSelect: true);
        }

        if (str_contains($query, 'SELECT DISTINCT ttp.np_communication_id')) {
            return new DailyCronJobTestStatement(function (): array {
                $this->rankedOwnerLookupCount++;

                return array_map(static fn (int $index): string => sprintf('NPWR%05d_00', $index), range(1, 101));
            }, isSelect: true);
        }

        if (str_contains($query, 'ranked_owners AS')) {
            return new DailyCronJobTestStatement(function (?array $params, array $boundValues): void {
                $json = $boundValues[':np_communication_ids'] ?? '[]';
                $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
                $this->fullRarityUpdateBatches[] = is_array($decoded) ? $decoded : [];
            });
        }

        if (str_contains($query, 'UPDATE trophy_meta tm')) {
            return new DailyCronJobTestStatement(function (?array $params, array $boundValues): void {
                $this->zeroOwnerRarityUpdates[] = $boundValues[':np_communication_id'] ?? '';
            });
        }

        if (str_contains($query, 'ttm.rarity_points = r.rarity_sum')) {
            return new DailyCronJobTestStatement(function (): void {
                $this->titlePointsUpdateCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class DailyCronJobTestStatement extends PDOStatement
{
    /** @var callable */
    private $callback;

    private bool $isSelect;

    private mixed $fetchColumnValue;

    /** @var array<string|int, mixed> */
    private array $boundValues = [];

    /**
     * @param callable $callback
     */
    public function __construct(callable $callback, bool $isSelect = false, mixed $fetchColumnValue = null)
    {
        $this->callback = $callback;
        $this->isSelect = $isSelect;
        $this->fetchColumnValue = $fetchColumnValue;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[$param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        if (!$this->isSelect) {
            ($this->callback)($params, $this->boundValues);
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (!$this->isSelect) {
            return [];
        }

        $result = ($this->callback)();

        return is_array($result) ? $result : [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->fetchColumnValue;
    }
}
