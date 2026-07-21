<?php

declare(strict_types=1);

final readonly class PsnpPlusGame
{
    public function __construct(
        final private int $id,
        final private string $npCommunicationId,
        final private string $name
    ) {}

    #[\NoDiscard]
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['np_communication_id'] ?? ''),
            (string) ($row['name'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
