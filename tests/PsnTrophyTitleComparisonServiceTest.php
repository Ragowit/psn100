<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyTitleComparisonService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyTitleComparisonRequestHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyTitleComparisonException.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

final class PsnTrophyTitleComparisonServiceTest extends TestCase
{
    public function testCompareByAccountIdFetchesAllPagesAndMeasuresTimeForDirectSource(): void
    {
        $worker = new Worker(1, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $capturedCalls = [];

        $client = new class($capturedCalls) {
            /**
             * @var array<int, array<string, mixed>>
             */
            public array $capturedCalls;

            public function __construct(array $capturedCalls)
            {
                $this->capturedCalls = $capturedCalls;
            }

            public function loginWithNpsso(string $npsso): void
            {
            }

            /**
             * @param array<string, mixed> $query
             * @param array<string, string> $headers
             */
            public function get(string $path, array $query = [], array $headers = []): array
            {
                $this->capturedCalls[] = [
                    'path' => $path,
                    'query' => $query,
                    'headers' => $headers,
                ];

                if (($query['offset'] ?? null) === 0) {
                    return [
                        'totalItemCount' => 3,
                        'trophyTitles' => [
                            ['npCommunicationId' => 'NPWR1'],
                            ['npCommunicationId' => 'NPWR2'],
                        ],
                    ];
                }

                return [
                    'totalItemCount' => 3,
                    'trophyTitles' => [
                        ['npCommunicationId' => 'NPWR3'],
                    ],
                ];
            }

            public function users(): object
            {
                return new class {
                    public function find(string $accountId): object
                    {
                        return new class {
                            public function trophyTitles(): object
                            {
                                return new class implements IteratorAggregate {
                                    public function getIterator(): Traversable
                                    {
                                        return new ArrayIterator([
                                            ['npCommunicationId' => 'NPWR1'],
                                            ['npCommunicationId' => 'NPWR2'],
                                            ['npCommunicationId' => 'NPWR3'],
                                        ]);
                                    }
                                };
                            }
                        };
                    }
                };
            }
        };

        $times = [10.0, 11.25];

        $service = new PsnTrophyTitleComparisonService(
            static fn (): array => [$worker],
            static fn () => $client,
            static function () use (&$times): float {
                return (float) array_shift($times);
            }
        );

        $result = $service->compareByAccountId('123456', PsnTrophyTitleComparisonService::SOURCE_DIRECT);

        $this->assertSame('123456', $result['accountId']);
        $this->assertSame(PsnTrophyTitleComparisonService::SOURCE_DIRECT, $result['source']);
        $this->assertSame(3, $result['result']['count']);
        $this->assertSame(2, $result['result']['pagesFetched']);
        $this->assertSame(1250.0, $result['result']['durationMs']);
    }


    public function testCompareByAccountIdCountsObjectBasedTustinTitles(): void
    {
        $worker = new Worker(1, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $client = new class {
            public function loginWithNpsso(string $npsso): void
            {
            }

            /**
             * @param array<string, mixed> $query
             * @param array<string, string> $headers
             */
            public function get(string $path, array $query = [], array $headers = []): array
            {
                return [
                    'totalItemCount' => 2,
                    'trophyTitles' => [
                        ['npCommunicationId' => 'NPWR10'],
                        ['npCommunicationId' => 'NPWR20'],
                    ],
                ];
            }

            public function users(): object
            {
                return new class {
                    public function find(string $accountId): object
                    {
                        return new class {
                            public function trophyTitles(): object
                            {
                                return new class implements IteratorAggregate {
                                    public function getIterator(): Traversable
                                    {
                                        return new ArrayIterator([
                                            new class {
                                                public function npCommunicationId(): string
                                                {
                                                    return 'NPWR10';
                                                }
                                            },
                                            new class {
                                                public function npCommunicationId(): string
                                                {
                                                    return 'NPWR20';
                                                }
                                            },
                                        ]);
                                    }
                                };
                            }
                        };
                    }
                };
            }
        };

        $times = [2.0, 2.5];

        $service = new PsnTrophyTitleComparisonService(
            static fn (): array => [$worker],
            static fn () => $client,
            static function () use (&$times): float {
                return (float) array_shift($times);
            }
        );

        $result = $service->compareByAccountId('123456', PsnTrophyTitleComparisonService::SOURCE_TUSTIN);

        $this->assertSame(PsnTrophyTitleComparisonService::SOURCE_TUSTIN, $result['source']);
        $this->assertSame(2, $result['result']['count']);
        $this->assertSame(500.0, $result['result']['durationMs']);
    }

    public function testCompareByAccountIdRejectsInvalidInput(): void
    {
        $service = new PsnTrophyTitleComparisonService(
            static fn (): array => [],
            static fn (): object => new stdClass()
        );

        try {
            $service->compareByAccountId('invalid', PsnTrophyTitleComparisonService::SOURCE_DIRECT);
            $this->fail('Expected InvalidArgumentException to be thrown for non numeric account IDs.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Account ID must be a numeric value.', $exception->getMessage());
        }
    }

    public function testCompareByAccountIdRejectsInvalidSource(): void
    {
        $service = new PsnTrophyTitleComparisonService(
            static fn (): array => [],
            static fn (): object => new stdClass()
        );

        try {
            $service->compareByAccountId('1234', 'invalid');
            $this->fail('Expected InvalidArgumentException to be thrown for invalid source.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Source must be either "direct" or "tustin".', $exception->getMessage());
        }
    }

    public function testRequestHandlerReturnsErrorMessageFromServiceException(): void
    {
        $worker = new Worker(1, '', 'npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnTrophyTitleComparisonService(
            static fn (): array => [$worker],
            static fn (): object => new class {
                public function loginWithNpsso(string $npsso): void
                {
                }
            }
        );

        $handled = PsnTrophyTitleComparisonRequestHandler::handle($service, '123', PsnTrophyTitleComparisonService::SOURCE_DIRECT);

        $this->assertSame('123', $handled->getNormalizedAccountId());
        $this->assertSame(PsnTrophyTitleComparisonService::SOURCE_DIRECT, $handled->getNormalizedSource());
        $this->assertSame(null, $handled->getResult());
        $this->assertSame('The PlayStation client does not support endpoint requests.', $handled->getErrorMessage());
    }

}
