<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Http/PlayStationHttpTransport.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Exception/PlayStationInvalidPayloadException.php';

final class PlayStationHttpTransportTest extends TestCase
{
    public function testFindUserByAccountIdMapsVendorUnauthorizedExceptionWithoutStatusCode(): void
    {
        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): array => [],
            accountLookupExecutor: static function (): object {
                throw new UnauthorizedHttpException('unauthorized');
            }
        );

        try {
            $transport->findUserByAccountId('123');
            $this->fail('Expected PlayStationAuthFailureException to be thrown.');
        } catch (PlayStationAuthFailureException) {
            $this->assertTrue(true);
        }
    }

    public function testFindUserByAccountIdMapsVendorNotFoundExceptionWithoutStatusCode(): void
    {
        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): array => [],
            accountLookupExecutor: static function (): object {
                throw new NotFoundHttpException('not found');
            }
        );

        try {
            $transport->findUserByAccountId('123');
            $this->fail('Expected PlayStationNotFoundException to be thrown.');
        } catch (PlayStationNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function testLookupUserProfileBuildsRequestAndValidatesPayload(): void
    {
        $capturedPath = null;
        $capturedQuery = null;
        $capturedHeaders = null;

        $transport = new PlayStationHttpTransport(
            requestExecutor: static function (string $path, array $query, array $headers) use (&$capturedPath, &$capturedQuery, &$capturedHeaders): array {
                $capturedPath = $path;
                $capturedQuery = $query;
                $capturedHeaders = $headers;

                return [
                    'profile' => [
                        'accountId' => '100',
                        'onlineId' => 'Hunter',
                        'currentOnlineId' => 'Hunter',
                        'npId' => base64_encode('hunter@a6.us'),
                    ],
                ];
            }
        );

        $result = $transport->lookupUserProfile('Hunter');

        $this->assertSame(
            'https://us-prof.np.community.playstation.net/userProfile/v1/users/Hunter/profile2',
            $capturedPath
        );
        $this->assertSame(['fields' => 'accountId,onlineId,currentOnlineId,npId'], $capturedQuery);
        $this->assertSame('application/json', $capturedHeaders['content-type'] ?? null);
        $this->assertSame('100', $result['profile']['accountId']);
    }

    public function testRequestRetriesAndInvokesRetryHook(): void
    {
        $attempt = 0;
        $retryCount = 0;

        $transport = new PlayStationHttpTransport(
            requestExecutor: static function () use (&$attempt): array {
                $attempt++;

                if ($attempt === 1) {
                    throw new RuntimeException('temporary');
                }

                return ['ok' => true];
            },
            maxAttempts: 2,
            onRetry: static function (string $path, int $retryAttempt, Throwable $exception) use (&$retryCount): void {
                $retryCount++;
            }
        );

        $result = $transport->request('https://example.com');

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $attempt);
        $this->assertSame(1, $retryCount);
    }

    public function testRequestRejectsMalformedPayload(): void
    {
        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): int => 123
        );

        try {
            $transport->request('https://example.com');
            $this->fail('Expected PlayStationInvalidPayloadException to be thrown.');
        } catch (PlayStationInvalidPayloadException) {
            $this->assertTrue(true);
        }
    }

    public function testSearchUsersReturnsDtos(): void
    {
        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): array => [],
            userSearchExecutor: static fn (): array => [
                ['onlineId' => 'PlayerOne', 'country' => 'us'],
                (object) ['onlineId' => 'PlayerTwo', 'country' => 'se'],
            ]
        );

        $results = array_values(iterator_to_array($transport->searchUsers('Player')));

        $this->assertCount(2, $results);
        $this->assertSame('PlayerOne', $results[0]->onlineId());
        $this->assertSame('se', $results[1]->country());
    }

    public function testSearchUsersSupportsMethodBackedObjectsWithoutJsonEncoding(): void
    {
        $user = new class {
            public function onlineId(): string
            {
                return 'MethodPlayer';
            }

            public function country(): string
            {
                return 'us';
            }
        };

        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): array => [],
            userSearchExecutor: static fn (): array => [$user]
        );

        $results = array_values(iterator_to_array($transport->searchUsers('MethodPlayer')));

        $this->assertCount(1, $results);
        $this->assertSame('MethodPlayer', $results[0]->onlineId());
        $this->assertSame('us', $results[0]->country());
    }

    public function testSearchUsersDoesNotInvokeAboutMeAccessorDuringMapping(): void
    {
        $user = new class {
            public function onlineId(): string
            {
                return 'NoBioFetch';
            }

            public function country(): string
            {
                return 'jp';
            }

            public function aboutMe(): string
            {
                throw new RuntimeException('aboutMe accessor should not be called while mapping search results.');
            }
        };

        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): array => [],
            userSearchExecutor: static fn (): array => [$user]
        );

        $results = array_values(iterator_to_array($transport->searchUsers('NoBioFetch')));

        $this->assertCount(1, $results);
        $this->assertSame('NoBioFetch', $results[0]->onlineId());
        $this->assertSame('jp', $results[0]->country());
        $this->assertSame(null, $results[0]->aboutMe());
    }

    public function testFindUserByAccountIdReturnsOriginalUserObject(): void
    {
        $user = new class {
            public function accountId(): string
            {
                return '123';
            }
        };

        $transport = new PlayStationHttpTransport(
            requestExecutor: static fn (): array => [],
            accountLookupExecutor: static fn (): object => $user
        );

        $result = $transport->findUserByAccountId('123');

        $this->assertSame($user, $result);
    }
}

final class UnauthorizedHttpException extends Exception
{
}

final class NotFoundHttpException extends Exception
{
}
