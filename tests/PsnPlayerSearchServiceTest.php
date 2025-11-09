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
                    }
                );
            }
        );

        $this->assertSame([], $service->search('   '));
    }

    public function testSearchReturnsUpToFiftyResults(): void
    {
        $worker = new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null);

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
            new Worker(1, 'invalid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null),
            new Worker(2, 'valid', '', new DateTimeImmutable('2024-01-02T00:00:00'), null),
        ];

        $userCollection = new StubUserCollection([
            'example' => [new StubUserSearchResult('Hunter', '42', 'SE')],
        ]);

        $clients = [
            new StubClient(
                $userCollection,
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
            $this->assertSame(
                'Admin player search failed while creating an authenticated client: RuntimeException: Unable to login to any worker accounts.',
                $exception->getMessage()
            );
        }
    }

    public function testSearchWrapsUserLookupFailuresWithDiagnosticMessage(): void
    {
        $worker = new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null);

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function (): object {
                return new StubClient(
                    new StubUserCollection([
                        'example' => static function (): iterable {
                            throw new RuntimeException('API offline');
                        },
                    ])
                );
            }
        );

        try {
            $service->search('example');
            $this->fail('Expected RuntimeException to be thrown when the search fails.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Admin player search failed while querying "example": RuntimeException: API offline',
                $exception->getMessage()
            );
        }
    }
}

final class StubClient
{
    private StubUserCollection $users;

    /** @var callable(string): void */
    private $loginHandler;

    public function __construct(StubUserCollection $users, ?callable $loginHandler = null)
    {
        $this->users = $users;
        $this->loginHandler = $loginHandler ?? static function (): void {
        };
    }

    public function loginWithNpsso(string $npsso): void
    {
        ($this->loginHandler)($npsso);
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
        $results = $this->resultsByQuery[$query] ?? [];

        if ($results instanceof Throwable) {
            throw $results;
        }

        if (is_callable($results)) {
            $results = $results();
        }

        return $results;
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
