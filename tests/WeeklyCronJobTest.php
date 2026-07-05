<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WeeklyCronJob.php';

final class WeeklyCronJobTest extends TestCase
{
    public function testUpdateQuerySnapshotsRankingsForActivePlayersOnly(): void
    {
        $class = new ReflectionClass(WeeklyCronJob::class);
        $query = $this->readPrivateConstantValue($class, 'UPDATE_PLAYER_RANKINGS_QUERY');

        $this->assertStringContainsString('UPDATE player p', $query);
        $this->assertStringContainsString('JOIN player_ranking r ON p.account_id = r.account_id', $query);
        $this->assertStringContainsString('p.rank_last_week = r.ranking', $query);
        $this->assertStringContainsString('WHERE p.status = 0', $query);
    }

    public function testResetQueryClearsWeeklyRankingsForInactivePlayers(): void
    {
        $class = new ReflectionClass(WeeklyCronJob::class);
        $query = $this->readPrivateConstantValue($class, 'RESET_INACTIVE_RANKINGS_QUERY');

        $this->assertStringContainsString('UPDATE', $query);
        $this->assertStringContainsString('p.rank_last_week = 0', $query);
        $this->assertStringContainsString('p.in_game_rarity_rank_country_last_week = 0', $query);
        $this->assertStringContainsString('WHERE', $query);
        $this->assertStringContainsString('p.status != 0', $query);
    }

    public function testRunUsesSeparateRetryLoopsForActiveAndInactiveUpdates(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/WeeklyCronJob.php');
        $this->assertTrue(is_string($source));
        $this->assertStringContainsString(
            '$this->executeWithRetry([$this, \'updateLeaderboardsForActivePlayers\']);',
            $source
        );
        $this->assertStringContainsString(
            '$this->executeWithRetry([$this, \'resetRankingsForInactivePlayers\']);',
            $source
        );
        $this->assertStringContainsString('while (true)', $source);
        $this->assertFalse(str_contains($source, 'maxAttempt'));
    }

    public function testRunRetriesOnlyInactiveResetAfterActiveSnapshotSucceeds(): void
    {
        $database = new WeeklyCronJobRetryTestDatabase();
        $sleepCalls = [];

        $job = new WeeklyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(1, $database->getActiveUpdateCount());
        $this->assertSame(3, $database->getInactiveResetCount());
    }

    public function testRunRetriesActiveSnapshotUntilItSucceeds(): void
    {
        $database = new WeeklyCronJobActiveRetryTestDatabase();
        $sleepCalls = [];

        $job = new WeeklyCronJob(
            $database,
            retryDelaySeconds: 1,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $job->run();

        $this->assertSame([1, 1], $sleepCalls);
        $this->assertSame(3, $database->getActiveUpdateCount());
        $this->assertSame(1, $database->getInactiveResetCount());
    }

    private function readPrivateConstantValue(ReflectionClass $class, string $name): string
    {
        $constant = $class->getReflectionConstant($name);
        $this->assertTrue($constant instanceof ReflectionClassConstant);
        $value = $constant->getValue();
        $this->assertTrue(is_string($value));

        return $value;
    }
}

final class WeeklyCronJobRetryTestDatabase extends PDO
{
    private int $activeUpdateCount = 0;

    private int $inactiveResetCount = 0;

    public function __construct()
    {
    }

    public function getActiveUpdateCount(): int
    {
        return $this->activeUpdateCount;
    }

    public function getInactiveResetCount(): int
    {
        return $this->inactiveResetCount;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'WHERE p.status = 0')) {
            return new WeeklyCronJobTestStatement(function (): void {
                $this->activeUpdateCount++;
            });
        }

        if (str_contains($query, 'p.status != 0')) {
            return new WeeklyCronJobTestStatement(function (): void {
                $this->inactiveResetCount++;

                if ($this->inactiveResetCount < 3) {
                    throw new RuntimeException('Simulated inactive reset failure.');
                }
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class WeeklyCronJobActiveRetryTestDatabase extends PDO
{
    private int $activeUpdateCount = 0;

    private int $inactiveResetCount = 0;

    public function __construct()
    {
    }

    public function getActiveUpdateCount(): int
    {
        return $this->activeUpdateCount;
    }

    public function getInactiveResetCount(): int
    {
        return $this->inactiveResetCount;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'WHERE p.status = 0')) {
            return new WeeklyCronJobTestStatement(function (): void {
                $this->activeUpdateCount++;

                if ($this->activeUpdateCount < 3) {
                    throw new RuntimeException('Simulated active snapshot failure.');
                }
            });
        }

        if (str_contains($query, 'p.status != 0')) {
            return new WeeklyCronJobTestStatement(function (): void {
                $this->inactiveResetCount++;
            });
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class WeeklyCronJobTestStatement extends PDOStatement
{
    /** @var callable(): void */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function execute(?array $params = null): bool
    {
        ($this->callback)();

        return true;
    }
}
