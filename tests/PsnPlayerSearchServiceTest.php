<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PsnApi/autoload.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnPlayerSearchRateLimitException.php';

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
            $searchResults[] = new StubUserSearchResult('Player' . $index, (string) $index, 'US', 'https://example.com/avatar.png', ['l' => 'https://example.com/avatar.png'], $index % 2 === 0, 'About me');
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
        $this->assertSame('https://example.com/avatar.png', $results[0]->getAvatarUrl());
        $this->assertSame(['l' => 'https://example.com/avatar.png'], $results[0]->getAvatars());
        $this->assertSame('About me', $results[0]->getAboutMe());
        $this->assertFalse($results[0]->isPlus());
        $this->assertTrue($results[1]->isPlus());
    }

    public function testSearchSkipsWorkersThatFailToLogin(): void
    {
        $workers = [
            new Worker(1, 'invalid', '', new DateTimeImmutable('2024-01-01T00:00:00'), null),
            new Worker(2, 'valid', '', new DateTimeImmutable('2024-01-02T00:00:00'), null),
        ];

        $userCollection = new StubUserCollection([
            'example' => [new StubUserSearchResult('Hunter', '42', 'SE', 'https://example.com/hunter.png', ['m' => 'https://example.com/hunter.png'], true, 'Hunter here')],
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
        $this->assertSame('Hunter here', $results[0]->getAboutMe());
        $this->assertTrue($results[0]->isPlus());
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
        $retryAt = new DateTimeImmutable('2024-07-15T12:30:00+00:00');

        $workers = [
            new Worker(1, 'valid', '', new DateTimeImmutable('2024-01-02T00:00:00'), null),
        ];

        $service = new PsnPlayerSearchService(
            static function () use ($workers): array {
                return $workers;
            },
            static function () use ($retryAt): object {
                return new RateLimitedClient($retryAt);
            }
        );

        try {
            $service->search('example');
            $this->fail('Expected rate limit exception when API returns 429.');
        } catch (PsnPlayerSearchRateLimitException $exception) {
            $this->assertSame($retryAt->getTimestamp(), $exception->getRetryAt()->getTimestamp());
        }
    }

    public function testControllerDisplaysRateLimitWarning(): void
    {
        $retryAt = new DateTimeImmutable('2024-07-15T12:30:00+00:00');

        $_GET['player'] = 'Example';
        $psnPlayerSearchService = new class ($retryAt)
        {
            private DateTimeImmutable $retryAt;

            public function __construct(DateTimeImmutable $retryAt)
            {
                $this->retryAt = $retryAt;
            }

            public function search(string $playerName): array
            {
                throw new PsnPlayerSearchRateLimitException($this->retryAt);
            }
        };

        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        header_remove();
        ob_start();

        require __DIR__ . '/../wwwroot/admin/psn-player-search.php';

        $output = (string) ob_get_clean();

        unset($_GET['player'], $psnPlayerSearchService);
        date_default_timezone_set($previousTimezone);

        $this->assertStringContainsString(
            'PSN search rate limited until 2024-07-15 12:30:00 UTC. Please wait before retrying.',
            $output
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

final class RateLimitedUserCollection
{
    private DateTimeImmutable $retryAt;

    public function __construct(DateTimeImmutable $retryAt)
    {
        $this->retryAt = $retryAt;
    }

    /**
     * @return iterable<object>
     */
    public function search(string $query): iterable
    {
        throw new PsnApi\HttpException(
            'GET',
            'https://example.com/users',
            429,
            '',
            'Too many requests.',
            null,
            ['X-RateLimit-Next-Available' => [$this->retryAt->format('U')]]
        );
    }
}

final class RateLimitedClient
{
    private RateLimitedUserCollection $users;

    public function __construct(DateTimeImmutable $retryAt)
    {
        $this->users = new RateLimitedUserCollection($retryAt);
    }

    public function loginWithNpsso(string $npsso): void
    {
    }

    public function users(): RateLimitedUserCollection
    {
        return $this->users;
    }
}

final class StubUserSearchResult
{
    private string $onlineId;

    private string $accountId;

    private string $country;

    private string $avatarUrl;

    /** @var array<string, string> */
    private array $avatars;

    private bool $isPlus;

    private string $aboutMe;

    /**
     * @param array<string, string> $avatars
     */
    public function __construct(string $onlineId, string $accountId, string $country, string $avatarUrl = '', array $avatars = [], bool $isPlus = false, string $aboutMe = '')
    {
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->country = $country;
        $this->avatarUrl = $avatarUrl;
        $this->avatars = $avatars;
        $this->isPlus = $isPlus;
        $this->aboutMe = $aboutMe;
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

    public function avatarUrl(): string
    {
        return $this->avatarUrl;
    }

    /**
     * @return array<string, string>
     */
    public function avatarUrls(): array
    {
        return $this->avatars;
    }

    public function hasPlus(): bool
    {
        return $this->isPlus;
    }

    public function aboutMe(): string
    {
        return $this->aboutMe;
    }
}
