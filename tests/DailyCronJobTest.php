<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php';

final class DailyCronJobTest extends TestCase
{
    public function testUpdateTrophyRarityQueryUsesTopTenThousandRankingFilter(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_QUERY');

        $this->assertStringContainsString('JOIN player_ranking pr ON pr.ranking <= 10000', $source);
        $this->assertStringContainsString('te.account_id = pr.account_id', $source);
        $this->assertStringContainsString('/ 10000.0) * 100 AS rarity_percent', $source);
        $this->assertStringContainsString('CAST(:np_communication_id AS CHAR(12))', $source);
        $this->assertStringContainsString('JOIN trophy t ON t.np_communication_id = title.np_communication_id', $source);
    }

    public function testUpdateTrophyRarityQueryDrivesTrophyEarnedByAccountIdForPartitionPruning(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_QUERY');

        $this->assertStringContainsString('ranked_owners AS', $source);
        $this->assertStringContainsString('INNER JOIN trophy_earned te', $source);
        $this->assertStringContainsString('GROUP BY te.order_id', $source);
        $this->assertFalse(str_contains($source, 'LEFT JOIN trophy_earned te'));
    }

    public function testZeroOwnersRarityQuerySkipsTrophyEarned(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_ZERO_OWNERS_QUERY');

        $this->assertStringContainsString('UPDATE trophy_meta tm', $source);
        $this->assertStringContainsString("tm.rarity_name = 'NONE'", $source);
        $this->assertFalse(str_contains($source, 'trophy_earned'));
        $this->assertFalse(str_contains($source, 'player_ranking'));
    }

    public function testUpdateTrophyRarityQueryAssignsRarityNamesFromThresholds(): void
    {
        $source = $this->readPrivateConstant('UPDATE_TROPHY_RARITY_QUERY');

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
        $this->assertStringContainsString(
            '$this->executeWithRetry([$this, \'updateTrophyRarityForGame\'], $npCommunicationId);',
            $source
        );
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
        $this->assertStringContainsString(
            '$this->executeWithRetry([$this, \'updateTrophyRarityForGame\'], $npCommunicationId);',
            $source
        );
        $this->assertStringContainsString(
            '$this->executeWithRetry([$this, \'updateTrophyTitleRarityPoints\']);',
            $source
        );
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

        if (str_contains($query, 'SELECT owners FROM trophy_title_meta')) {
            // Non-zero owners force the full ranked trophy_earned rarity path.
            return new DailyCronJobTestStatement(static function (): int {
                return 10;
            }, isSelect: true, fetchColumnValue: 10);
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

        if (str_contains($query, 'SELECT owners FROM trophy_title_meta')) {
            return new DailyCronJobTestStatement(static function (): int {
                return 0;
            }, isSelect: true, fetchColumnValue: 0);
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

final class DailyCronJobTestStatement extends PDOStatement
{
    /** @var callable(): mixed */
    private $callback;

    private bool $isSelect;

    private mixed $fetchColumnValue;

    /**
     * @param callable(): mixed $callback
     */
    public function __construct(callable $callback, bool $isSelect = false, mixed $fetchColumnValue = null)
    {
        $this->callback = $callback;
        $this->isSelect = $isSelect;
        $this->fetchColumnValue = $fetchColumnValue;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if (!$this->isSelect) {
            ($this->callback)();
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
