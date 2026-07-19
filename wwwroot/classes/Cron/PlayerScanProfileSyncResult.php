<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanProfileSyncStatus.php';

/**
 * Outcome of resolving and persisting a queued player's PSN profile during a scan.
 */
final readonly class PlayerScanProfileSyncResult
{
    /**
     * @param array<string, mixed> $player
     */
    private function __construct(
        final public PlayerScanProfileSyncStatus $status,
        final public array $player = [],
        final public ?object $user = null,
        final public ?string $country = null,
    ) {
    }

    /**
     * @param array<string, mixed> $player
     */
    #[\NoDiscard]
    public static function success(array $player, object $user, string $country): self
    {
        return new self(PlayerScanProfileSyncStatus::Success, $player, $user, $country);
    }

    #[\NoDiscard]
    public static function skipPlayer(): self
    {
        return new self(PlayerScanProfileSyncStatus::SkipPlayer);
    }

    public function isSuccess(): bool
    {
        return $this->status === PlayerScanProfileSyncStatus::Success;
    }

    public function shouldSkipPlayer(): bool
    {
        return $this->status === PlayerScanProfileSyncStatus::SkipPlayer;
    }
}
