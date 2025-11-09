<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchRateLimitException.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchRequestHandler.php';
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
            $this->assertSame('Unable to login to any worker accounts.', $exception->getMessage());
        }
    }

    public function testSearchThrowsRateLimitExceptionWithRetryTimestamp(): void
    {
        $worker = new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null);
        $retryAt = new DateTimeImmutable('2024-01-01T01:00:00+00:00');

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($retryAt): object {
                $response = new StubRateLimitResponse(
                    429,
                    ['X-RateLimit-Next-Available' => [$retryAt->format(DateTimeInterface::RFC3339)]]
                );

                $userCollection = new RateLimitedUserCollection(new StubRateLimitedException($response));

                return new StubClient($userCollection);
            }
        );

        try {
            $service->search('example');
            $this->fail('Expected PsnPlayerSearchRateLimitException to be thrown.');
        } catch (PsnPlayerSearchRateLimitException $exception) {
            $this->assertSame(
                $retryAt->format(DateTimeInterface::RFC3339),
                $exception->getRetryAt()?->format(DateTimeInterface::RFC3339)
            );
        }
    }

    public function testRequestHandlerReturnsRateLimitWarning(): void
    {
        $worker = new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null);
        $retryAt = new DateTimeImmutable('2024-01-01T02:00:00+00:00');

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($retryAt): object {
                $response = new StubRateLimitResponse(
                    429,
                    ['X-RateLimit-Next-Available' => [$retryAt->format(DateTimeInterface::RFC3339)]]
                );

                $userCollection = new RateLimitedUserCollection(new StubRateLimitedException($response));

                return new StubClient($userCollection);
            }
        );

        $handled = PsnPlayerSearchRequestHandler::handle($service, ' example ');

        $this->assertSame('example', $handled['normalizedSearchTerm']);
        $this->assertSame([], $handled['results']);
        $this->assertSame(
            sprintf(
                'The PlayStation Network rate limited player search until %s.',
                $retryAt->format(DateTimeInterface::RFC3339)
            ),
            $handled['errorMessage']
        );
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

class StubUserCollection
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

final class RateLimitedUserCollection extends StubUserCollection
{
    private Throwable $throwable;

    public function __construct(Throwable $throwable)
    {
        parent::__construct([]);
        $this->throwable = $throwable;
    }

    public function search(string $query): iterable
    {
        throw $this->throwable;
    }
}

final class StubRateLimitResponse
{
    private int $statusCode;

    /**
     * @var array<string, list<string>>
     */
    private array $headers;

    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(int $statusCode, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = [];

        foreach ($headers as $name => $values) {
            $normalizedName = strtolower($name);
            $this->headers[$normalizedName] = array_values(array_map('strval', $values));
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /**
     * @return list<string>
     */
    public function getHeader(string $name): array
    {
        $normalizedName = strtolower($name);

        return $this->headers[$normalizedName] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }
}

final class StubRateLimitedException extends RuntimeException
{
    private object $response;

    public function __construct(object $response)
    {
        parent::__construct('Too Many Requests', 429);
        $this->response = $response;
    }

    public function getResponse(): object
    {
        return $this->response;
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
