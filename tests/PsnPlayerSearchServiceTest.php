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
                    graphQlHandler: static function (): object {
                        throw new RuntimeException('Client should not attempt to perform GraphQL requests.');
                    },
                    loginHandler: static function (): void {
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

        $players = [];
        for ($index = 1; $index <= 60; $index++) {
            $players[] = [
                'onlineId' => 'Player' . $index,
                'accountId' => (string) $index,
                'country' => 'US',
            ];
        }

        $contextResponse = self::createContextSearchResponse(array_slice($players, 0, 20), 'cursor-1');
        $firstDomainResponse = self::createDomainSearchResponse(array_slice($players, 20, 20), 'cursor-2');
        $secondDomainResponse = self::createDomainSearchResponse(array_slice($players, 40, 20), null);

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($contextResponse, $firstDomainResponse, $secondDomainResponse): object {
                $graphQlHandler = static function (string $path, array $query, array $headers = []) use (
                    $contextResponse,
                    $firstDomainResponse,
                    $secondDomainResponse
                ): object {
                    $operationName = $query['operationName'] ?? '';
                    $variables = PsnPlayerSearchServiceTest::decodeVariables($query);
                    $searchTerm = (string) ($variables['searchTerm'] ?? '');

                    if ($searchTerm !== 'example') {
                        return (object) [];
                    }

                    if ($operationName === 'metGetContextSearchResults') {
                        return $contextResponse;
                    }

                    if ($operationName === 'metGetDomainSearchResults') {
                        $nextCursor = (string) ($variables['nextCursor'] ?? '');

                        if ($nextCursor === 'cursor-1') {
                            return $firstDomainResponse;
                        }

                        if ($nextCursor === 'cursor-2') {
                            return $secondDomainResponse;
                        }
                    }

                    return (object) [];
                };

                return new StubClient($graphQlHandler);
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

        $graphQlHandler = static function (string $path, array $query, array $headers = []): object {
            $variables = PsnPlayerSearchServiceTest::decodeVariables($query);

            if (($variables['searchTerm'] ?? '') !== 'example') {
                return (object) [];
            }

            return PsnPlayerSearchServiceTest::createContextSearchResponse([
                ['onlineId' => 'Hunter', 'accountId' => '42', 'country' => 'SE'],
            ]);
        };

        $clients = [
            new StubClient(
                graphQlHandler: $graphQlHandler,
                loginHandler: static function (): void {
                    throw new RuntimeException('Invalid credentials.');
                }
            ),
            new StubClient($graphQlHandler),
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
                return new StubClient();
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

        $graphQlHandler = static function () use ($retryAt): object {
            $response = new StubRateLimitResponse(
                429,
                ['X-RateLimit-Next-Available' => [$retryAt->format(DateTimeInterface::RFC3339)]]
            );

            throw new StubRateLimitedException($response);
        };

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($graphQlHandler): object {
                return new StubClient($graphQlHandler);
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

        $graphQlHandler = static function () use ($retryAt): object {
            $response = new StubRateLimitResponse(
                429,
                ['X-RateLimit-Next-Available' => [$retryAt->format(DateTimeInterface::RFC3339)]]
            );

            throw new StubRateLimitedException($response);
        };

        $service = new PsnPlayerSearchService(
            static function () use ($worker): array {
                return [$worker];
            },
            static function () use ($graphQlHandler): object {
                return new StubClient($graphQlHandler);
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

    /**
     * @param list<array{onlineId: string, accountId: string, country: string}> $players
     */
    private static function createContextSearchResponse(array $players, ?string $nextCursor = null): object
    {
        return (object) [
            'data' => (object) [
                'universalContextSearch' => (object) [
                    'results' => [
                        self::createDomainResponseBody($players, $nextCursor),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param list<array{onlineId: string, accountId: string, country: string}> $players
     */
    private static function createDomainSearchResponse(array $players, ?string $nextCursor = null): object
    {
        return (object) [
            'data' => (object) [
                'universalDomainSearch' => self::createDomainResponseBody($players, $nextCursor),
            ],
        ];
    }

    /**
     * @param list<array{onlineId: string, accountId: string, country: string}> $players
     */
    private static function createDomainResponseBody(array $players, ?string $nextCursor): object
    {
        return (object) [
            '__typename' => 'UniversalDomainSearchResponse',
            'domain' => 'SocialAllAccounts',
            'domainTitle' => 'Players',
            'next' => $nextCursor,
            'searchResults' => array_map([self::class, 'createSearchResultItem'], $players),
            'totalResultCount' => count($players),
            'zeroState' => false,
        ];
    }

    /**
     * @param array{onlineId: string, accountId: string, country: string} $player
     */
    private static function createSearchResultItem(array $player): object
    {
        return (object) [
            '__typename' => 'SearchResultItem',
            'highlight' => (object) [],
            'id' => $player['accountId'],
            'result' => (object) [
                '__typename' => 'Player',
                'accountId' => $player['accountId'],
                'country' => $player['country'],
                'onlineId' => $player['onlineId'],
            ],
            'resultOriginFlag' => null,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private static function decodeVariables(array $query): array
    {
        $variablesRaw = $query['variables'] ?? '{}';

        if (!is_string($variablesRaw)) {
            return [];
        }

        $decoded = json_decode($variablesRaw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

final class StubClient
{
    /** @var callable(string): void */
    private $loginHandler;

    /** @var callable(string, array, array): object */
    private $graphQlHandler;

    public function __construct(?callable $graphQlHandler = null, ?callable $loginHandler = null)
    {
        $this->graphQlHandler = $graphQlHandler ?? static function (): object {
            return (object) [];
        };

        $this->loginHandler = $loginHandler ?? static function (): void {
        };
    }

    public function loginWithNpsso(string $npsso): void
    {
        ($this->loginHandler)($npsso);
    }

    public function get(string $path = '', array $query = [], array $headers = []): object
    {
        return ($this->graphQlHandler)($path, $query, $headers);
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
