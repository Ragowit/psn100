<?php

declare(strict_types=1);

readonly class PsnpPlusGame
{
    public function __construct(
        private int $id,
        private string $npCommunicationId,
        private string $name
    ) {}

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
