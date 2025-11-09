<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

final class PsnPlayerSearchServiceTest extends TestCase
{
    public function testSearchReturnsEmptyArrayWhenQueryIsBlank(): void
    {
        $service = new PsnPlayerSearchService(
            static function (): array {
                return [];
            },
            static function (): object {
                return new StubClient(
                    new StubUserCollection([]),
                    static function (): void {
                        throw new RuntimeException('Client should not attempt to authenticate.');
                    },
                    static function (): void {
                        throw new RuntimeException('Client should not attempt to authenticate.');
                    }
                );
            }
        );

        $this->assertSame([], $service->search('   '));
    }

    public function testSearchReturnsUpToFiftyResults(): void
    {
        $worker = new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-01T00:00:00'));

        $searchResults = [];
        for ($index = 1; $index <= 60; $index++) {
            $searchResults[] = new StubUserSearchResult('Player' . $index, (string) $index, 'US');
        }

        $userCollection = new StubUserCollection(['example' => $searchResults]);

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($userCollection): object {
                return new StubClient($userCollection);
            }
        );

        $results = $service->search('example');

        $this->assertCount(50, $results);
        $this->assertSame('Player1', $results[0]->getOnlineId());
        $this->assertSame('Player50', $results[49]->getOnlineId());
    }

    public function testSearchSkipsWorkersThatFailToLogin(): void
    {
        $workers = [
            new Worker(1, 'invalid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null, 'refresh-token-invalid'),
            new Worker(2, 'valid', '', new DateTimeImmutable('2024-01-02T00:00:00')),
        ];

        $userCollection = new StubUserCollection([
            'example' => [new StubUserSearchResult('Hunter', '42', 'SE')],
        ]);

        $clients = [
            new StubClient(
                $userCollection,
                static function (): void {
                    throw new RuntimeException('Invalid credentials.');
                },
                static function (): void {
                    throw new RuntimeException('Invalid credentials.');
                }
            ),
            new StubClient($userCollection),
        ];

        $service = new PsnPlayerSearchService(
            static function () use ($workers): array {
                return $workers;
            },
            static function () use (&$clients): object {
                if ($clients === []) {
                    throw new RuntimeException('No client available.');
                }

                return array_shift($clients);
            }
        );

        $results = $service->search('example');

        $this->assertCount(1, $results);
        $this->assertSame('Hunter', $results[0]->getOnlineId());
    }

    public function testSearchFallsBackToRefreshTokenWhenNpssoFails(): void
    {
        $worker = new Worker(
            1,
            'bad-npsso',
            '',
            new DateTimeImmutable('2024-01-01T00:00:00'),
            null,
            'good-refresh-token'
        );

        $userCollection = new StubUserCollection([
            'example' => [new StubUserSearchResult('Fallback', '314', 'SE')],
        ]);

        $attempts = [];

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($userCollection, &$attempts): object {
                return new StubClient(
                    $userCollection,
                    static function (string $npsso) use (&$attempts): void {
                        $attempts[] = ['type' => 'npsso', 'value' => $npsso];

                        throw new RuntimeException('NPSSO login failed.');
                    },
                    static function (string $refreshToken) use (&$attempts): void {
                        $attempts[] = ['type' => 'refresh', 'value' => $refreshToken];
                    }
                );
            }
        );

        $results = $service->search('example');

        $this->assertCount(1, $results);
        $this->assertSame('Fallback', $results[0]->getOnlineId());
        $this->assertSame(
            [
                ['type' => 'npsso', 'value' => 'bad-npsso'],
                ['type' => 'refresh', 'value' => 'good-refresh-token'],
            ],
            $attempts
        );
    }
 
    public function testSearchThrowsWhenNoWorkersCanAuthenticate(): void
    {
        $service = new PsnPlayerSearchService(
            static function (): array {
                return [];
            },
            static function (): object {
                return new StubClient(new StubUserCollection([]));
            }
        );

        try {
            $service->search('example');
            $this->fail('Expected RuntimeException to be thrown when no worker can authenticate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to login to any worker accounts.', $exception->getMessage());
        }
    }
}

final class StubClient
{
    private StubUserCollection $users;

    /** @var callable(string): void */
    private $npssoLoginHandler;

    /** @var callable(string): void */
    private $refreshTokenLoginHandler;

    public function __construct(
        StubUserCollection $users,
        ?callable $npssoLoginHandler = null,
        ?callable $refreshTokenLoginHandler = null
    ) {
        $this->users = $users;
        $this->npssoLoginHandler = $npssoLoginHandler ?? static function (): void {
        };
        $this->refreshTokenLoginHandler = $refreshTokenLoginHandler ?? static function (): void {
        };
    }

    public function loginWithNpsso(string $npsso): void
    {
        ($this->npssoLoginHandler)($npsso);
    }

    public function loginWithRefreshToken(string $refreshToken): void
    {
        ($this->refreshTokenLoginHandler)($refreshToken);
    }

    public function users(): StubUserCollection
    {
        return $this->users;
    }
}

final class StubUserCollection
{
    /**
     * @var array<string, list<object>>
     */
    private array $resultsByQuery;

    /**
     * @param array<string, list<object>> $resultsByQuery
     */
    public function __construct(array $resultsByQuery)
    {
        $this->resultsByQuery = $resultsByQuery;
    }

    /**
     * @return iterable<object>
     */
    public function search(string $query): iterable
    {
        return $this->resultsByQuery[$query] ?? [];
    }
}

final class StubUserSearchResult
{
    private string $onlineId;

    private string $accountId;

    private string $country;

    public function __construct(string $onlineId, string $accountId, string $country)
    {
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->country = $country;
    }

    public function onlineId(): string
    {
        return $this->onlineId;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function country(): string
    {
        return $this->country;
    }
}
