<?php

declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';

final class PlayerQueuePollTokenManager
{
    private const string SESSION_KEY = 'queue_poll_tokens';

    private const int TOKEN_TTL_SECONDS = 3600;

    #[\NoDiscard]
    public function issue(string $playerName): string
    {
        SessionManager::ensureStarted();

        $token = bin2hex(random_bytes(32));
        $tokens = $this->readTokens();
        $this->purgeExpired($tokens);
        $tokens[$playerName] = [
            'token' => $token,
            'expires' => $this->clock()->getTimestamp() + self::TOKEN_TTL_SECONDS,
        ];
        $_SESSION[self::SESSION_KEY] = $tokens;

        return $token;
    }

    public function validate(string $playerName, string $submittedToken): bool
    {
        if ($playerName === '' || $submittedToken === '') {
            return false;
        }

        SessionManager::ensureStarted();

        $tokens = $this->readTokens();
        $this->purgeExpired($tokens);
        $_SESSION[self::SESSION_KEY] = $tokens;

        $entry = $tokens[$playerName] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        $storedToken = $entry['token'] ?? '';
        if (!is_string($storedToken) || $storedToken === '') {
            return false;
        }

        return hash_equals($storedToken, $submittedToken);
    }

    /**
     * @return array<string, array{token: string, expires: int}>
     */
    private function readTokens(): array
    {
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($tokens) ? $tokens : [];
    }

    /**
     * @param array<string, array{token?: string, expires?: int}> $tokens
     */
    private function purgeExpired(array &$tokens): void
    {
        $now = $this->clock()->getTimestamp();

        foreach ($tokens as $playerName => $entry) {
            $expires = is_array($entry) ? (int) ($entry['expires'] ?? 0) : 0;

            if ($expires <= $now) {
                unset($tokens[$playerName]);
            }
        }
    }

    private function clock(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
