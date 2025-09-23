<?php

declare(strict_types=1);

class PsnpPlusGame
{
    private int $id;
    private string $npCommunicationId;
    private string $name;

    public function __construct(int $id, string $npCommunicationId, string $name)
    {
        $this->id = $id;
        $this->npCommunicationId = $npCommunicationId;
        $this->name = $name;
    }

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
