<?php

declare(strict_types=1);

require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/WorkerService.php';

use Tustin\PlayStation\Client;

/**
 * Authenticates PlayStation API worker accounts and persists refreshed tokens.
 *
 * Centralizes login orchestration that was previously duplicated across
 * PsnGameLookupService, GameRescanService, and ThirtyMinuteCronJob.
 */
final class PlayStationWorkerAuthenticator
{
    private const \Closure DEFAULT_CLIENT_FACTORY = static function (): object {
        return new Client();
    };

    private const \Closure DEFAULT_REFRESH_TOKEN_SAVER = static function (int $workerId, string $refreshToken): void {
    };

    private const \Closure DEFAULT_SLEEPER = static function (int $seconds): void {
        sleep($seconds);
    };

    /**
     * @var \Closure(): iterable<Worker>
     */
    private readonly \Closure $workerFetcher;

    /**
     * @var \Closure(): object
     */
    private readonly \Closure $clientFactory;

    /**
     * @var \Closure(int, string): void
     */
    private readonly \Closure $refreshTokenSaver;

    /**
     * @var \Closure(int): void
     */
    private readonly \Closure $sleeper;

    /**
     * @var \Closure(int, string): void|null
     */
    private readonly ?\Closure $refreshTokenPersistenceFailureHandler;

    public function __construct(
        callable $workerFetcher,
        ?callable $clientFactory = null,
        ?callable $refreshTokenSaver = null,
        ?callable $sleeper = null,
        ?callable $refreshTokenPersistenceFailureHandler = null,
    ) {
        $this->workerFetcher = \Closure::fromCallable($workerFetcher);
        $this->clientFactory = \Closure::fromCallable($clientFactory ?? self::DEFAULT_CLIENT_FACTORY);
        $this->refreshTokenSaver = \Closure::fromCallable($refreshTokenSaver ?? self::DEFAULT_REFRESH_TOKEN_SAVER);
        $this->sleeper = \Closure::fromCallable($sleeper ?? self::DEFAULT_SLEEPER);
        $this->refreshTokenPersistenceFailureHandler = $refreshTokenPersistenceFailureHandler !== null
            ? \Closure::fromCallable($refreshTokenPersistenceFailureHandler)
            : null;
    }

    public static function fromWorkerService(
        WorkerService $workerService,
        ?callable $clientFactory = null,
        ?callable $refreshTokenPersistenceFailureHandler = null,
    ): self {
        return new self(
            static fn (): array => $workerService->fetchWorkers(),
            $clientFactory,
            static function (int $workerId, string $refreshToken) use ($workerService): void {
                $workerService->updateWorkerRefreshToken($workerId, $refreshToken);
            },
            null,
            $refreshTokenPersistenceFailureHandler,
        );
    }

  /** @return object Authenticated PlayStation API client. */
    public function authenticateWorker(Worker $worker): object
    {
        $refreshToken = $worker->getRefreshToken();
        $npsso = $worker->getNpsso();

        if ($refreshToken === '' && $npsso === '') {
            throw new RuntimeException('Worker has no credentials configured.');
        }

        $client = ($this->clientFactory)();
        $this->loginClient($client, $refreshToken, $npsso);
        $this->persistRefreshTokenBestEffort($worker->getId(), $client);

        return $client;
    }

  /** @return object Authenticated PlayStation API client. */
    public function authenticateWithNextAvailableWorker(): object
    {
        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                continue;
            }

            try {
                return $this->authenticateWorker($worker);
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Unable to login to any worker accounts.');
    }

  /**
   * @param callable(int, Throwable): void|null $onLoginFailure
   * @return object Authenticated PlayStation API client.
   */
    public function authenticateWithRetry(
        int $retryDelaySeconds = 300,
        ?callable $onLoginFailure = null,
    ): object {
        $onLoginFailureClosure = $onLoginFailure !== null
            ? \Closure::fromCallable($onLoginFailure)
            : null;

        while (true) {
            foreach (($this->workerFetcher)() as $worker) {
                if (!$worker instanceof Worker) {
                    continue;
                }

                try {
                    return $this->authenticateWorker($worker);
                } catch (TypeError) {
                    // Something odd, try next worker.
                } catch (Throwable $exception) {
                    if ($onLoginFailureClosure !== null) {
                        $onLoginFailureClosure($worker->getId(), $exception);
                    }
                }
            }

            ($this->sleeper)($retryDelaySeconds);
        }
    }

    private function loginClient(object $client, string $refreshToken, string $npsso): void
    {
        if ($refreshToken !== '' && method_exists($client, 'loginWithRefreshToken')) {
            try {
                $client->loginWithRefreshToken($refreshToken);

                return;
            } catch (Throwable $exception) {
                if ($npsso === '') {
                    throw $exception;
                }
            }
        }

        if ($npsso === '') {
            throw new RuntimeException('Worker has no credentials configured.');
        }

        if (!method_exists($client, 'loginWithNpsso')) {
            throw new RuntimeException('The PlayStation client does not support NPSSO authentication.');
        }

        $client->loginWithNpsso($npsso);
    }

    private function persistRefreshTokenBestEffort(int $workerId, object $client): void
    {
        try {
            $this->saveRefreshToken($workerId, $client);
        } catch (Throwable $exception) {
            if ($this->refreshTokenPersistenceFailureHandler !== null) {
                ($this->refreshTokenPersistenceFailureHandler)($workerId, $exception->getMessage());
            }
        }
    }

    private function saveRefreshToken(int $workerId, object $client): void
    {
        if (!method_exists($client, 'getRefreshToken')) {
            return;
        }

        $refreshToken = $client->getRefreshToken();
        if (!is_object($refreshToken) || !method_exists($refreshToken, 'getToken')) {
            return;
        }

        $tokenValue = $refreshToken->getToken();
        if (!is_string($tokenValue) || $tokenValue === '') {
            return;
        }

        ($this->refreshTokenSaver)($workerId, $tokenValue);
    }
}
