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
    private ?string $originalPersistedQueryHash = null;

    private ?string $originalOperationName = null;

    protected function setUp(): void
    {
        parent::setUp();

        $persistedHash = getenv('PSN_PLAYER_SEARCH_PERSISTED_QUERY_HASH');
        $operationName = getenv('PSN_PLAYER_SEARCH_OPERATION_NAME');

        $this->originalPersistedQueryHash = $persistedHash === false ? null : $persistedHash;
        $this->originalOperationName = $operationName === false ? null : $operationName;

        putenv('PSN_PLAYER_SEARCH_PERSISTED_QUERY_HASH=test-hash');
        putenv('PSN_PLAYER_SEARCH_OPERATION_NAME=searchUniversalSearch');
    }

    protected function tearDown(): void
    {
        if ($this->originalPersistedQueryHash === null || $this->originalPersistedQueryHash === '') {
            putenv('PSN_PLAYER_SEARCH_PERSISTED_QUERY_HASH');
        } else {
            putenv('PSN_PLAYER_SEARCH_PERSISTED_QUERY_HASH=' . $this->originalPersistedQueryHash);
        }

        if ($this->originalOperationName === null || $this->originalOperationName === '') {
            putenv('PSN_PLAYER_SEARCH_OPERATION_NAME');
        } else {
            putenv('PSN_PLAYER_SEARCH_OPERATION_NAME=' . $this->originalOperationName);
        }

        parent::tearDown();
    }

    public function testSearchReturnsEmptyArrayWhenQueryIsBlank(): void
    {
        $service = new PsnPlayerSearchService(
            static function (): array {
                return [];
            },
            static function (): object {
                return new StubClient([], static function (): void {
                    throw new RuntimeException('Client should not attempt to authenticate.');
                });
            }
        );

        $this->assertSame([], $service->search('   '));
    }

    public function testSearchReturnsUpToFiftyResults(): void
    {
        $worker = new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null);

        $searchResults = [];

        for ($index = 1; $index <= 60; $index++) {
            $searchResults[] = [
                'onlineId' => 'Player' . $index,
                'accountId' => (string) $index,
                'country' => 'US',
            ];
        }

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($searchResults): object {
                return new StubClient([
                    createGraphQlResponse($searchResults),
                ]);
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

        $clients = [
            new StubClient(
                [],
                static function (): void {
                    throw new RuntimeException('Invalid credentials.');
                }
            ),
            new StubClient([
                createGraphQlResponse([
                    [
                        'onlineId' => 'Hunter',
                        'accountId' => '42',
                        'country' => 'SE',
                    ],
                ]),
            ]),
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
                return new StubClient([]);
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

                return new StubClient([
                    new StubRateLimitedException($response),
                ]);
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

                return new StubClient([
                    new StubRateLimitedException($response),
                ]);
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
    /** @var list<object|array|Throwable> */
    private array $responses;

    /** @var callable(string): void */
    private $loginHandler;

    /** @var StubGraphQlRequest|null */
    private $lastRequest = null;

    /**
     * @param list<object|array|Throwable> $responses
     * @param callable(string): void|null $loginHandler
     */
    public function __construct(array $responses, ?callable $loginHandler = null)
    {
        $this->responses = array_values($responses);
        $this->loginHandler = $loginHandler ?? static function (): void {
        };
    }

    public function loginWithNpsso(string $npsso): void
    {
        ($this->loginHandler)($npsso);
    }

    public function get(string $path = '', array $query = [], array $headers = []): mixed
    {
        if ($path !== 'graphql/v1/op') {
            throw new RuntimeException('Unexpected GraphQL endpoint: ' . $path);
        }

        $this->lastRequest = new StubGraphQlRequest($query, $headers);

        if ($this->responses === []) {
            return [];
        }

        $next = array_shift($this->responses);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function getLastRequest(): ?StubGraphQlRequest
    {
        return $this->lastRequest;
    }
}

final class StubGraphQlRequest
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $headers;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $headers
     */
    public function __construct(array $query, array $headers)
    {
        $this->query = $query;
        $this->headers = $headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
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

/**
 * @param list<array{onlineId: string, accountId: string, country: string}> $results
 */
function createGraphQlResponse(array $results): array
{
    $graphQlResults = [];

    foreach ($results as $result) {
        $graphQlResults[] = [
            'socialMetadata' => [
                'onlineId' => $result['onlineId'],
                'accountId' => $result['accountId'],
                'country' => $result['country'],
            ],
        ];
    }

    return [
        'data' => [
            'searchUniversalSearch' => [
                'domainResponses' => [
                    [
                        'results' => $graphQlResults,
                    ],
                ],
            ],
        ],
    ];
}
