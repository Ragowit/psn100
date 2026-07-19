<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueStatus.php';

final readonly class PlayerQueueResponse implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>>|null $messageParts
     */
    private function __construct(
        final private PlayerQueueStatus $status,
        final private string $message,
        final private ?array $messageParts = null,
        final private ?string $pollToken = null,
        final private int $httpStatusCode = 200,
    ) {}

    /**
     * @param list<array<string, mixed>>|null $messageParts
     */
    #[\NoDiscard]
    public static function queued(string $message, ?array $messageParts = null): self
    {
        return new self(PlayerQueueStatus::QUEUED, $message, $messageParts);
    }

    /**
     * @param list<array<string, mixed>>|null $messageParts
     */
    #[\NoDiscard]
    public static function complete(string $message, ?array $messageParts = null): self
    {
        return new self(PlayerQueueStatus::COMPLETE, $message, $messageParts);
    }

    #[\NoDiscard]
    public static function error(string $message, ?array $messageParts = null): self
    {
        return new self(PlayerQueueStatus::ERROR, $message, $messageParts);
    }

    #[\NoDiscard]
    public static function busy(string $message): self
    {
        return new self(PlayerQueueStatus::ERROR, $message, null, null, 503);
    }

    #[\NoDiscard]
    public static function rateLimited(string $message): self
    {
        return new self(PlayerQueueStatus::ERROR, $message, null, null, 429);
    }

    #[\NoDiscard]
    public function withPollToken(string $pollToken): self
    {
        return clone($this, ['pollToken' => $pollToken]);
    }

    public function getStatusEnum(): PlayerQueueStatus
    {
        return $this->status;
    }

    public function getStatus(): string
    {
        return $this->status->value;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getMessageParts(): ?array
    {
        return $this->messageParts;
    }

    public function getPollToken(): ?string
    {
        return $this->pollToken;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function shouldPoll(): bool
    {
        return $this->status === PlayerQueueStatus::QUEUED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'status' => $this->getStatus(),
            'message' => $this->getMessage(),
            'shouldPoll' => $this->shouldPoll(),
        ];

        if ($this->messageParts !== null) {
            $payload['messageParts'] = $this->messageParts;
        }

        if ($this->pollToken !== null) {
            $payload['pollToken'] = $this->pollToken;
        }

        return $payload;
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
