<?php

declare(strict_types=1);

class PlayerQueueResponse
{
    private const STATUS_QUEUED = 'queued';
    private const STATUS_COMPLETE = 'complete';
    private const STATUS_ERROR = 'error';

    private string $status;

    private string $message;

    private function __construct(string $status, string $message)
    {
        $this->status = $status;
        $this->message = $message;
    }

    public static function queued(string $message): self
    {
        return new self(self::STATUS_QUEUED, $message);
    }

    public static function complete(string $message): self
    {
        return new self(self::STATUS_COMPLETE, $message);
    }

    public static function error(string $message): self
    {
        return new self(self::STATUS_ERROR, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function shouldPoll(): bool
    {
        return $this->status === self::STATUS_QUEUED;
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
}
