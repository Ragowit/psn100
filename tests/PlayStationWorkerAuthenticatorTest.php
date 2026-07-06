<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

final class PlayStationWorkerAuthenticatorTest extends TestCase
{
    public function testAuthenticateWorkerUsesRefreshToken(): void
    {
        $worker = new Worker(1, 'stored-refresh-token', 'stored-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $clients = [];

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static function () use (&$clients): object {
                $client = new WorkerAuthStubClient();
                $clients[] = $client;

                return $client;
            },
        );

        $authenticator->authenticateWorker($worker);

        $this->assertSame(['refresh:stored-refresh-token'], $clients[0]->loginMethods);
    }

    public function testAuthenticateWorkerFallsBackToNpssoWhenRefreshTokenFails(): void
    {
        $worker = new Worker(2, 'expired-refresh-token', 'valid-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $clients = [];

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static function () use (&$clients): object {
                $client = new WorkerAuthStubClient(refreshTokenShouldFail: true);
                $clients[] = $client;

                return $client;
            },
        );

        $authenticator->authenticateWorker($worker);

        $this->assertSame(['refresh:expired-refresh-token', 'npsso:valid-npsso'], $clients[0]->loginMethods);
    }

    public function testAuthenticateWorkerThrowsWhenNoCredentialsConfigured(): void
    {
        $worker = new Worker(3, '', '', '', new DateTimeImmutable('2024-01-01'), null);

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static fn (): object => new WorkerAuthStubClient(),
        );

        try {
            $authenticator->authenticateWorker($worker);
            $this->fail('Expected RuntimeException when worker has no credentials.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Worker has no credentials configured.', $exception->getMessage());
        }
    }

    public function testAuthenticateWorkerPersistsRefreshTokenBestEffort(): void
    {
        $worker = new Worker(4, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $savedTokens = [];

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static fn (): object => new WorkerAuthStubClient(
                refreshTokenToReturn: 'new-refresh-token',
            ),
            static function (int $workerId, string $refreshToken) use (&$savedTokens): void {
                $savedTokens[] = ['workerId' => $workerId, 'refreshToken' => $refreshToken];
            },
        );

        $authenticator->authenticateWorker($worker);

        $this->assertSame([['workerId' => 4, 'refreshToken' => 'new-refresh-token']], $savedTokens);
    }

    public function testAuthenticateWorkerDoesNotFailWhenRefreshTokenPersistenceFails(): void
    {
        $worker = new Worker(5, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $failureMessages = [];

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static fn (): object => new WorkerAuthStubClient(refreshTokenToReturn: 'issued-token'),
            static function (): void {
                throw new RuntimeException('Database unavailable.');
            },
            null,
            static function (int $workerId, string $message) use (&$failureMessages): void {
                $failureMessages[] = ['workerId' => $workerId, 'message' => $message];
            },
        );

        $authenticator->authenticateWorker($worker);

        $this->assertSame(1, count($failureMessages));
        $this->assertSame(5, $failureMessages[0]['workerId']);
        $this->assertSame('Database unavailable.', $failureMessages[0]['message']);
    }

    public function testAuthenticateWithNextAvailableWorkerTriesWorkersInOrder(): void
    {
        $firstWorker = new Worker(10, '', '', '', new DateTimeImmutable('2024-01-01'), null);
        $secondWorker = new Worker(11, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $clients = [];

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$firstWorker, $secondWorker],
            static function () use (&$clients): object {
                $client = new WorkerAuthStubClient();
                $clients[] = $client;

                return $client;
            },
        );

        $authenticator->authenticateWithNextAvailableWorker();

        $this->assertSame(['npsso:valid-npsso'], $clients[0]->loginMethods);
    }

    public function testAuthenticateWithNextAvailableWorkerThrowsWhenAllWorkersFail(): void
    {
        $worker = new Worker(12, '', '', '', new DateTimeImmutable('2024-01-01'), null);

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static fn (): object => new WorkerAuthStubClient(),
        );

        try {
            $authenticator->authenticateWithNextAvailableWorker();
            $this->fail('Expected RuntimeException when all workers fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to login to any worker accounts.', $exception->getMessage());
        }
    }

    public function testAuthenticateWithRetrySleepsBetweenAttempts(): void
    {
        $worker = new Worker(13, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $sleepDurations = [];
        $attempts = 0;

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static function () use (&$attempts): object {
                $attempts++;

                return new WorkerAuthStubClient(
                    shouldFailUntilAttempt: $attempts < 2 ? 1 : 0,
                );
            },
            null,
            static function (int $seconds) use (&$sleepDurations): void {
                $sleepDurations[] = $seconds;
            },
        );

        $authenticator->authenticateWithRetry(42);

        $this->assertSame([42], $sleepDurations);
        $this->assertSame(2, $attempts);
    }

    public function testAuthenticateWithRetryInvokesFailureHandler(): void
    {
        $failingWorker = new Worker(14, '', '', '', new DateTimeImmutable('2024-01-01'), null);
        $workingWorker = new Worker(15, '', 'valid-npsso', '', new DateTimeImmutable('2024-01-01'), null);
        $failureEvents = [];

        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$failingWorker, $workingWorker],
            static fn (): object => new WorkerAuthStubClient(),
        );

        $authenticator->authenticateWithRetry(
            7,
            static function (int $workerId, Throwable $exception) use (&$failureEvents): void {
                $failureEvents[] = ['workerId' => $workerId, 'message' => $exception->getMessage()];
            },
        );

        $this->assertSame(1, count($failureEvents));
        $this->assertSame(14, $failureEvents[0]['workerId']);
        $this->assertSame('Worker has no credentials configured.', $failureEvents[0]['message']);
    }
}

final class WorkerAuthStubClient
{
    /** @var list<string> */
    public array $loginMethods = [];

    public function __construct(
        private readonly bool $refreshTokenShouldFail = false,
        private readonly string $refreshTokenToReturn = '',
        private readonly int $shouldFailUntilAttempt = 0,
    ) {
    }

    public function loginWithRefreshToken(string $refreshToken): void
    {
        $this->recordLogin('refresh', $refreshToken);

        if ($this->refreshTokenShouldFail) {
            throw new RuntimeException('Refresh token expired.');
        }
    }

    public function loginWithNpsso(string $npsso): void
    {
        $this->recordLogin('npsso', $npsso);
    }

    public function getRefreshToken(): object
    {
        return new class ($this->refreshTokenToReturn) {
            public function __construct(private readonly string $token)
            {
            }

            public function getToken(): string
            {
                return $this->token;
            }
        };
    }

    private function recordLogin(string $method, string $value): void
    {
        if ($this->shouldFailUntilAttempt > 0) {
            throw new RuntimeException('Temporary login failure.');
        }

        $this->loginMethods[] = $method . ':' . $value;
    }
}
