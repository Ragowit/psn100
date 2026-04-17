<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayStationUserSearchResult.php';

final class PlayStationHttpTransport
{
    private const array DEFAULT_HEADERS = ['content-type' => 'application/json'];

    private readonly Closure $requestExecutor;

    private readonly ?Closure $accountLookupExecutor;

    private readonly ?Closure $userSearchExecutor;

    private readonly ?Closure $beforeRequest;

    private readonly ?Closure $afterResponse;

    private readonly ?Closure $onRetry;

    /**
     * @param callable(string, array<string, scalar|null>, array<string, string>): mixed $requestExecutor
     * @param null|callable(string): mixed $accountLookupExecutor
     * @param null|callable(string): iterable<mixed> $userSearchExecutor
     * @param null|callable(string, array<string, scalar|null>, array<string, string>): void $beforeRequest
     * @param null|callable(string, array<string, scalar|null>, array<string, string>, array<string, mixed>): void $afterResponse
     * @param null|callable(string, int, Throwable): void $onRetry
     */
    public function __construct(
        callable $requestExecutor,
        ?callable $accountLookupExecutor = null,
        ?callable $userSearchExecutor = null,
        private readonly int $maxAttempts = 1,
        ?callable $beforeRequest = null,
        ?callable $afterResponse = null,
        ?callable $onRetry = null,
        private readonly int $decodeDepth = 512,
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }

        $this->requestExecutor = Closure::fromCallable($requestExecutor);
        $this->accountLookupExecutor = $accountLookupExecutor !== null ? Closure::fromCallable($accountLookupExecutor) : null;
        $this->userSearchExecutor = $userSearchExecutor !== null ? Closure::fromCallable($userSearchExecutor) : null;
        $this->beforeRequest = $beforeRequest !== null ? Closure::fromCallable($beforeRequest) : null;
        $this->afterResponse = $afterResponse !== null ? Closure::fromCallable($afterResponse) : null;
        $this->onRetry = $onRetry !== null ? Closure::fromCallable($onRetry) : null;
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function lookupUserProfile(string $onlineId, array $query = [], array $headers = []): array
    {
        $normalizedOnlineId = trim($onlineId);

        if ($normalizedOnlineId === '') {
            throw new InvalidArgumentException('Online ID cannot be blank.');
        }

        $payload = $this->request(
            sprintf(
                'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/profile2',
                rawurlencode($normalizedOnlineId)
            ),
            $query !== [] ? $query : ['fields' => 'accountId,onlineId,currentOnlineId,npId'],
            $headers
        );

        $profile = $payload['profile'] ?? null;

        if (!is_array($profile)) {
            throw new UnexpectedValueException('Malformed profile response from PlayStation Network.');
        }

        $this->assertOptionalStringField($profile, 'accountId');
        $this->assertOptionalStringField($profile, 'onlineId');
        $this->assertOptionalStringField($profile, 'currentOnlineId');
        $this->assertOptionalStringField($profile, 'npId');

        return $payload;
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function lookupTrophyList(string $npCommunicationId, array $query = [], array $headers = []): array
    {
        return $this->request(
            sprintf(
                'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/all/trophies',
                rawurlencode(trim($npCommunicationId))
            ),
            $query,
            $headers
        );
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function lookupTrophyGroups(string $npCommunicationId, array $query = [], array $headers = []): array
    {
        return $this->request(
            sprintf(
                'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups',
                rawurlencode(trim($npCommunicationId))
            ),
            $query,
            $headers
        );
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function lookupTrophiesByGroup(
        string $npCommunicationId,
        string $groupId,
        array $query = [],
        array $headers = []
    ): array {
        return $this->request(
            sprintf(
                'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/%s/trophies',
                rawurlencode(trim($npCommunicationId)),
                rawurlencode(trim($groupId))
            ),
            $query,
            $headers
        );
    }

    public function findUserByAccountId(string $accountId): PlayStationUserSearchResult
    {
        if ($this->accountLookupExecutor === null) {
            throw new RuntimeException('Account lookup is not configured for this transport.');
        }

        $payload = $this->decodePayload(($this->accountLookupExecutor)($accountId));

        return PlayStationUserSearchResult::fromPayload($payload);
    }

    /**
     * @return iterable<PlayStationUserSearchResult>
     */
    public function searchUsers(string $onlineId): iterable
    {
        if ($this->userSearchExecutor === null) {
            return [];
        }

        $results = ($this->userSearchExecutor)($onlineId);

        foreach ($results as $result) {
            yield PlayStationUserSearchResult::fromPayload($this->decodePayload($result));
        }
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function request(string $path, array $query = [], array $headers = []): array
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Request path cannot be blank.');
        }

        $mergedHeaders = $this->normalizeHeaders($headers);

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                if ($this->beforeRequest !== null) {
                    ($this->beforeRequest)($path, $query, $mergedHeaders);
                }

                $payload = $this->decodePayload(($this->requestExecutor)($path, $query, $mergedHeaders));

                if ($this->afterResponse !== null) {
                    ($this->afterResponse)($path, $query, $mergedHeaders, $payload);
                }

                return $payload;
            } catch (Throwable $throwable) {
                $lastException = $throwable;

                if ($attempt >= $this->maxAttempts) {
                    break;
                }

                if ($this->onRetry !== null) {
                    ($this->onRetry)($path, $attempt, $throwable);
                }
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('PlayStation request failed before execution.');
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = self::DEFAULT_HEADERS;

        foreach ($headers as $key => $value) {
            if (!is_string($key) || !is_string($value) || trim($key) === '') {
                throw new InvalidArgumentException('Invalid request header.');
            }

            $normalizedHeaders[strtolower($key)] = $value;
        }

        return $normalizedHeaders;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload)) {
            return $this->decodeJsonString($payload);
        }

        if (is_object($payload)) {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            if (!is_string($encoded)) {
                throw new UnexpectedValueException('Unable to encode PlayStation response object.');
            }

            return $this->decodeJsonString($encoded);
        }

        throw new UnexpectedValueException('Malformed PlayStation response payload.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonString(string $payload): array
    {
        if (trim($payload) === '') {
            throw new UnexpectedValueException('Empty PlayStation response payload.');
        }

        $decoded = json_decode($payload, true, $this->decodeDepth, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new UnexpectedValueException('PlayStation response payload must decode to an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertOptionalStringField(array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        if (!is_string($payload[$field]) || $payload[$field] === '') {
            throw new UnexpectedValueException(sprintf('Invalid profile field "%s" in PlayStation response.', $field));
        }
    }
}
