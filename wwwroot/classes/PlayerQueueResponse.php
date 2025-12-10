<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerQueueStatus.php';

readonly class PlayerQueueResponse implements \JsonSerializable
{
    private function __construct(
        private PlayerQueueStatus $status,
        private string $message,
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

    public function shouldPoll(): bool
    {
        return $this->status === PlayerQueueStatus::QUEUED;
    }

    /**
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->getStatus(),
            'message' => $this->getMessage(),
            'shouldPoll' => $this->shouldPoll(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
