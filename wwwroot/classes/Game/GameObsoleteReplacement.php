<?php

declare(strict_types=1);

final readonly class GameObsoleteReplacement
{
    private function __construct(
        final private int $id,
        final private string $name,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['name'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
