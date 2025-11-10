<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerLookupService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerLookupRequestHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

final class PsnPlayerLookupServiceTest extends TestCase
{
    public function testLookupReturnsProfileDataAsArray(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $capturedPath = null;
        $capturedQuery = null;
        $capturedHeaders = null;

        $service = new PsnPlayerLookupService(
            static fn (): array => [$worker],
            function () use (&$capturedPath, &$capturedQuery, &$capturedHeaders): object {
                return new StubClient(
                    profileHandler: function (string $path, array $query, array $headers) use (&$capturedPath, &$capturedQuery, &$capturedHeaders): object {
                        $capturedPath = $path;
                        $capturedQuery = $query;
                        $capturedHeaders = $headers;

                        return (object) [
                            'profile' => (object) [
                                'onlineId' => 'Example',
                                'accountId' => '1234567890',
                                'currentOnlineId' => 'ExampleNew',
                                'npId' => base64_encode('example@a6.us'),
                            ],
                        ];
                    }
                );
            }
        );

        $result = $service->lookup(' Example ');

        $this->assertSame(
            'https://us-prof.np.community.playstation.net/userProfile/v1/users/Example/profile2',
            $capturedPath
        );
        $this->assertSame(['fields' => 'accountId,onlineId,currentOnlineId,npId'], $capturedQuery);
        $this->assertSame(['content-type' => 'application/json'], $capturedHeaders);
        $this->assertSame('Example', $result['profile']['onlineId']);
        $this->assertSame('1234567890', $result['profile']['accountId']);
        $this->assertSame('ExampleNew', $result['profile']['currentOnlineId']);
    }

    public function testLookupThrowsNotFoundException(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnPlayerLookupService(
            static fn (): array => [$worker],
            static fn (): object => new StubClient(
                profileHandler: static function (string $path = '', array $query = [], array $headers = []): object {
                    $response = new StubResponse(404);

                    throw new StubHttpException('Not Found', $response);
                }
            )
        );

        try {
            $service->lookup('missing-player');
            $this->fail('Expected PsnPlayerLookupException to be thrown.');
        } catch (PsnPlayerLookupException $exception) {
            $this->assertSame('Player "missing-player" was not found.', $exception->getMessage());
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function testLookupSkipsWorkersThatFailToAuthenticate(): void
    {
        $workers = [
            new Worker(1, 'bad-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null),
            new Worker(2, 'good-npsso', '', new DateTimeImmutable('2024-01-02T00:00:00+00:00'), null),
        ];

        $clients = [
            new StubClient(
                profileHandler: static function (string $path, array $query, array $headers): object {
                    throw new RuntimeException('Client should not perform profile requests when login fails.');
                },
                loginHandler: static function (): void {
                    throw new RuntimeException('Invalid credentials.');
                }
            ),
            new StubClient(
                profileHandler: static function (string $path = '', array $query = [], array $headers = []): object {
                    return (object) [
                        'profile' => (object) [
                            'onlineId' => 'Hunter',
                            'accountId' => '42',
                        ],
                    ];
                }
            ),
        ];

        $service = new PsnPlayerLookupService(
            static fn (): array => $workers,
            static function () use (&$clients): object {
                if ($clients === []) {
                    throw new RuntimeException('No more clients available.');
                }

                return array_shift($clients);
            }
        );

        $result = $service->lookup('Hunter');

        $this->assertSame('Hunter', $result['profile']['onlineId']);
        $this->assertSame('42', $result['profile']['accountId']);
    }

    public function testLookupThrowsWhenNoWorkerCanAuthenticate(): void
    {
        $service = new PsnPlayerLookupService(
            static fn (): array => [],
            static fn (): object => new StubClient()
        );

        try {
            $service->lookup('Example');
            $this->fail('Expected RuntimeException to be thrown when no worker can authenticate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to login to any worker accounts.', $exception->getMessage());
        }
    }

    public function testRequestHandlerReturnsNullForBlankInput(): void
    {
        $worker = new Worker(1, 'npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnPlayerLookupService(
            static fn (): array => [$worker],
            static fn (): object => new StubClient()
        );

        $handled = PsnPlayerLookupRequestHandler::handle($service, '   ');

        $this->assertSame('', $handled['normalizedOnlineId']);
        $this->assertSame(null, $handled['result']);
        $this->assertSame(null, $handled['errorMessage']);
    }

    public function testRequestHandlerReturnsLookupErrorMessage(): void
    {
        $worker = new Worker(1, 'npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnPlayerLookupService(
            static fn (): array => [$worker],
            static fn (): object => new StubClient(
                profileHandler: static function (string $path = '', array $query = [], array $headers = []): object {
                    $response = new StubResponse(404);

                    throw new StubHttpException('Not Found', $response);
                }
            )
        );

        $handled = PsnPlayerLookupRequestHandler::handle($service, 'missing');

        $this->assertSame('missing', $handled['normalizedOnlineId']);
        $this->assertSame(null, $handled['result']);
        $this->assertSame('Player "missing" was not found.', $handled['errorMessage']);
    }

    public function testRequestHandlerReturnsFallbackMessageForUnexpectedErrors(): void
    {
        $service = new PsnPlayerLookupService(
            static function (): array {
                throw new RuntimeException('');
            },
            static fn (): object => new StubClient()
        );

        $handled = PsnPlayerLookupRequestHandler::handle($service, 'example');

        $this->assertSame('example', $handled['normalizedOnlineId']);
        $this->assertSame(null, $handled['result']);
        $this->assertSame(
            'An unexpected error occurred while looking up the player. Please try again later.',
            $handled['errorMessage']
        );
    }
}

final class StubClient
{
    /** @var callable(string, array, array): object */
    private $profileHandler;

    /** @var callable(string): void */
    private $loginHandler;

    public function __construct(?callable $profileHandler = null, ?callable $loginHandler = null)
    {
        $this->profileHandler = $profileHandler ?? static function (): object {
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
        return ($this->profileHandler)($path, $query, $headers);
    }
}

final class StubResponse
{
    private int $statusCode;

    public function __construct(int $statusCode)
    {
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

final class StubHttpException extends RuntimeException
{
    private StubResponse $response;

    public function __construct(string $message, StubResponse $response)
    {
        parent::__construct($message);
        $this->response = $response;
    }

    public function getResponse(): StubResponse
    {
        return $this->response;
    }
}
