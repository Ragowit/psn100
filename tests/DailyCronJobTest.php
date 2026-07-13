<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php';

final class DailyCronJobTest extends TestCase
{
    public function testUpdateTrophyRarityQueryUsesTopTenThousandRankingFilter(): void
    {
        $source = $this->readMethodSource('updateTrophyRarityForGame');

        $this->assertStringContainsString('LEFT JOIN player_ranking p', $source);
        $this->assertStringContainsString('p.ranking <= 10000', $source);
        $this->assertStringContainsString('/ 10000.0) * 100 AS rarity_percent', $source);
    }

    public function testUpdateTrophyRarityQueryAssignsRarityNamesFromThresholds(): void
    {
        $source = $this->readMethodSource('updateTrophyRarityForGame');

        $this->assertStringContainsString("WHEN r.rarity_percent > 10 THEN 'COMMON'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 2 THEN 'UNCOMMON'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 0.2 THEN 'RARE'", $source);
        $this->assertStringContainsString("WHEN r.rarity_percent > 0.02 THEN 'EPIC'", $source);
        $this->assertStringContainsString("ELSE 'LEGENDARY'", $source);
        $this->assertStringContainsString("WHEN r.in_game_rarity_percent <= 1 THEN 'LEGENDARY'", $source);
    }

    public function testUpdateTrophyTitleRarityPointsAggregatesPerGame(): void
    {
        $source = $this->readMethodSource('updateTrophyTitleRarityPoints');

        $this->assertStringContainsString('IFNULL(SUM(tm.rarity_point), 0) AS rarity_sum', $source);
        $this->assertStringContainsString('IFNULL(SUM(tm.in_game_rarity_point), 0) AS in_game_rarity_sum', $source);
        $this->assertStringContainsString('GROUP BY t.np_communication_id', $source);
        $this->assertStringContainsString('ttm.rarity_points = r.rarity_sum', $source);
        $this->assertStringContainsString('ttm.in_game_rarity_points = r.in_game_rarity_sum', $source);
    }

    public function testExecuteWithRetryUsesInfiniteLoopWithoutMaxAttemptCap(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/DailyCronJob.php');
        $this->assertTrue(is_string($source));

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

    private function readMethodSource(string $methodName): string
    {
        $class = new ReflectionClass(DailyCronJob::class);
        $method = $class->getMethod($methodName);
        $source = file_get_contents((string) $class->getFileName());
        $this->assertTrue(is_string($source));

        $lines = explode("\n", $source);
        $startLine = $method->getStartLine() - 1;
        $lineCount = $method->getEndLine() - $method->getStartLine() + 1;

        return implode("\n", array_slice($lines, $startLine, $lineCount));
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

    /**
     * @param callable(): mixed $callback
     */
    public function __construct(callable $callback, bool $isSelect = false)
    {
        $this->callback = $callback;
        $this->isSelect = $isSelect;
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
}
