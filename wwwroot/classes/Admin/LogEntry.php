<?php

declare(strict_types=1);

final readonly class LogEntry
{
    public function __construct(
        final private int $id,
        final private DateTimeImmutable $time,
        final private string $formattedMessage,
    ) {
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
