<?php

declare(strict_types=1);

final class LogEntry
{
    private int $id;

    private DateTimeImmutable $time;

    private string $formattedMessage;

    public function __construct(int $id, DateTimeImmutable $time, string $formattedMessage)
    {
        $this->id = $id;
        $this->time = $time;
        $this->formattedMessage = $formattedMessage;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getFormattedMessage(): string
    {
        return $this->formattedMessage;
    }
}
