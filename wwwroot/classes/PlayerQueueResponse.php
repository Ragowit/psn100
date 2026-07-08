<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueStatus.php';

readonly class PlayerQueueResponse implements \JsonSerializable
{
    private function __construct(
        private PlayerQueueStatus $status,
        private string $message,
        private ?string $pollToken = null,
        private int $httpStatusCode = 200,
    ) {}

    public static function queued(string $message): self
    {
        return new self(PlayerQueueStatus::QUEUED, $message);
    }

    public static function complete(string $message): self
    {
        return new self(PlayerQueueStatus::COMPLETE, $message);
    }

    public static function error(string $message): self
    {
        return new self(PlayerQueueStatus::ERROR, $message);
    }

    public static function rateLimited(string $message): self
    {
        return new self(PlayerQueueStatus::ERROR, $message, null, 429);
    }

    public function withPollToken(string $pollToken): self
    {
        return new self(
            $this->status,
            $this->message,
            $pollToken,
            $this->httpStatusCode,
        );
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
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        $payload = [
            'status' => $this->getStatus(),
            'message' => $this->getMessage(),
            'shouldPoll' => $this->shouldPoll(),
        ];

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
