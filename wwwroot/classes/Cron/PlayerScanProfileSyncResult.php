<?php

declare(strict_types=1);

/**
 * Outcome of resolving and persisting a queued player's PSN profile during a scan.
 */
final class PlayerScanProfileSyncResult
{
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_SKIP_PLAYER = 'skip_player';

    /**
     * @param array<string, mixed> $player
     */
    private function __construct(
        final public readonly string $status,
        final public readonly array $player = [],
        final public readonly ?object $user = null,
        final public readonly ?string $country = null,
    ) {
    }

    /**
     * @param array<string, mixed> $player
     */
    #[\NoDiscard]
    public static function success(array $player, object $user, string $country): self
    {
        return new self(self::STATUS_SUCCESS, $player, $user, $country);
    }

    #[\NoDiscard]
    public static function skipPlayer(): self
    {
        return new self(self::STATUS_SKIP_PLAYER);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function shouldSkipPlayer(): bool
    {
        return $this->status === self::STATUS_SKIP_PLAYER;
    }
}
