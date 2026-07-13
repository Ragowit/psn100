<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageService.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPagePlayer.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageScanSummary.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

/**
 * @extends PDO
 */
final class AboutPageServicePdoStub extends PDO
{
    /** @var string|int */
    private string|int $scannedCount;

    /** @var string|int */
    private string|int $newCount;

    /** @var list<array<string, mixed>> */
    private array $scanLogRows;

    /** @var list<int> */
    private array $boundLimits = [];

    /** @var list<int> */
    private array $boundLimitTypes = [];

    /**
     * @param string|int $scannedCount
     * @param string|int $newCount
     * @param list<array<string, mixed>> $scanLogRows
     */
    public function __construct(string|int $scannedCount, string|int $newCount, array $scanLogRows)
    {
        $this->scannedCount = $scannedCount;
        $this->newCount = $newCount;
        $this->scanLogRows = $scanLogRows;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'SELECT COUNT(*)')) {
            return new class($this->scannedCount, $this->newCount) extends PDOStatement {
                /** @var string|int */
                private string|int $scannedCount;

                /** @var string|int */
                private string|int $newCount;

                /**
                 * @param string|int $scannedCount
                 * @param string|int $newCount
                 */
                public function __construct(string|int $scannedCount, string|int $newCount)
                {
                    $this->scannedCount = $scannedCount;
                    $this->newCount = $newCount;
                }

                public function execute(?array $params = null): bool
                {
                    return true;
                }

                public function fetch(
                    int $mode = PDO::ATTR_DEFAULT_FETCH_MODE,
                    int $cursorOrientation = PDO::FETCH_ORI_NEXT,
                    int $cursorOffset = 0
                ): mixed {
                    return [
                        'scanned_players' => $this->scannedCount,
                        'new_players' => $this->newCount,
                    ];
                }
            };
        }

        return new class($this, $this->scanLogRows) extends PDOStatement {
            private AboutPageServicePdoStub $pdo;

            /** @var list<array<string, mixed>> */
            private array $rows;

            private int $limit = PHP_INT_MAX;

            /**
             * @param list<array<string, mixed>> $rows
             */
            public function __construct(AboutPageServicePdoStub $pdo, array $rows)
            {
                $this->pdo = $pdo;
                $this->rows = $rows;
            }

            public function bindValue($param, $value, $type = PDO::PARAM_STR): bool
            {
                if ($param === ':limit') {
                    $this->limit = (int) $value;
                    $this->pdo->recordBoundLimit($this->limit, $type);
                }

                return true;
            }

            public function execute(?array $params = null): bool
            {
                return true;
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
            {
                return array_slice($this->rows, 0, $this->limit);
            }
        };
    }

    public function recordBoundLimit(int $limit, int $type): void
    {
        $this->boundLimits[] = $limit;
        $this->boundLimitTypes[] = $type;
    }

    /**
     * @return list<int>
     */
    public function getBoundLimits(): array
    {
        return $this->boundLimits;
    }

    /**
     * @return list<int>
     */
    public function getBoundLimitTypes(): array
    {
        return $this->boundLimitTypes;
    }
}

final class RecordingUtilityStub extends Utility
{
    /** @var list<string|null> */
    private array $receivedCountryCodes = [];

    public function getCountryName(?string $countryCode): string
    {
        $this->receivedCountryCodes[] = $countryCode;

        if ($countryCode === null || $countryCode === '') {
            return 'Country(Unknown)';
        }

        return 'Country(' . strtoupper($countryCode) . ')';
    }

    /**
     * @return list<string|null>
     */
    public function getReceivedCountryCodes(): array
    {
        return $this->receivedCountryCodes;
    }
}

final class AboutPageServiceTest extends TestCase
{
    public function testGetScanSummaryReturnsCountsFromQueries(): void
    {
        $pdo = new AboutPageServicePdoStub('15', 'not numeric', []);
        $service = new AboutPageService($pdo, new Utility());

        $summary = $service->getScanSummary();

        $this->assertSame(15, $summary->getScannedPlayers());
        $this->assertSame(0, $summary->getNewPlayers());
    }

    public function testGetScanLogPlayersConvertsDatabaseRowsIntoAboutPagePlayers(): void
    {
        $rows = [
            [
                'online_id' => 'PlayerOne',
                'country' => 'us',
                'avatar_url' => '/avatar1.png',
                'last_updated_date' => '2024-03-01T10:00:00',
                'level' => 400,
                'progress' => '25%',
                'rank_last_week' => 10,
                'status' => 0,
                'trophy_count_npwr' => 100,
                'trophy_count_sony' => 120,
                'ranking' => 8,
            ],
            [
                'online_id' => 'NewPlayer',
                'country' => null,
                'avatar_url' => '/avatar2.png',
                'last_updated_date' => null,
                'level' => null,
                'progress' => null,
                'rank_last_week' => 0,
                'status' => 3,
                'trophy_count_npwr' => 5,
                'trophy_count_sony' => 5,
                'ranking' => null,
            ],
        ];

        $pdo = new AboutPageServicePdoStub(0, 0, $rows);
        $utility = new RecordingUtilityStub();
        $service = new AboutPageService($pdo, $utility);

        $players = $service->getScanLogPlayers(2);

        $this->assertSame([2], $pdo->getBoundLimits());
        $this->assertSame([PDO::PARAM_INT], $pdo->getBoundLimitTypes());
        $this->assertCount(2, $players);

        $firstPlayer = $players[0];
        $this->assertSame('PlayerOne', $firstPlayer->getOnlineId());
        $this->assertSame('us', $firstPlayer->getCountryCode());
        $this->assertSame('/avatar1.png', $firstPlayer->getAvatarUrl());
        $this->assertSame('2024-03-01T10:00:00', $firstPlayer->getLastUpdatedDate());
        $this->assertSame(400, $firstPlayer->getLevel());
        $this->assertSame('25%', $firstPlayer->getProgress());
        $this->assertTrue($firstPlayer->isRanked());
        $this->assertSame(8, $firstPlayer->getRanking());
        $this->assertTrue($firstPlayer->hasHiddenTrophies());
        $this->assertSame(0, $firstPlayer->getStatus());
        $this->assertSame(null, $firstPlayer->getStatusLabel());
        $this->assertFalse($firstPlayer->isNew());
        $this->assertSame(2, $firstPlayer->getRankDelta());
        $this->assertSame('#0bd413', $firstPlayer->getRankDeltaColor());
        $this->assertSame('(+2)', $firstPlayer->getRankDeltaLabel());
        $this->assertSame('Country(US)', $firstPlayer->getCountryName());

        $secondPlayer = $players[1];
        $this->assertSame('NewPlayer', $secondPlayer->getOnlineId());
        $this->assertSame('', $secondPlayer->getCountryCode());
        $this->assertSame('/avatar2.png', $secondPlayer->getAvatarUrl());
        $this->assertSame(null, $secondPlayer->getLastUpdatedDate());
        $this->assertSame(null, $secondPlayer->getLevel());
        $this->assertSame(null, $secondPlayer->getProgress());
        $this->assertFalse($secondPlayer->isRanked());
        $this->assertSame(null, $secondPlayer->getRanking());
        $this->assertFalse($secondPlayer->hasHiddenTrophies());
        $this->assertSame(3, $secondPlayer->getStatus());
        $this->assertSame('Private', $secondPlayer->getStatusLabel());
        $this->assertTrue($secondPlayer->isNew());
        $this->assertSame(null, $secondPlayer->getRankDelta());
        $this->assertSame(null, $secondPlayer->getRankDeltaColor());
        $this->assertSame(null, $secondPlayer->getRankDeltaLabel());
        $this->assertSame('Country(Unknown)', $secondPlayer->getCountryName());

        $this->assertSame(['us', ''], $utility->getReceivedCountryCodes());
    }

    public function testGetScanLogPlayersUsesDefaultLimitWhenNotProvided(): void
    {
        $pdo = new AboutPageServicePdoStub(0, 0, []);
        $service = new AboutPageService($pdo, new Utility());

        $players = $service->getScanLogPlayers();

        $this->assertSame([], $players);
        $this->assertSame([10], $pdo->getBoundLimits());
    }
}
