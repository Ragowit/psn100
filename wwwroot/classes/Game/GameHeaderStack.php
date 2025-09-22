<?php

declare(strict_types=1);

class GameHeaderStack
{
    private int $id;
    private string $name;
    private string $platform;
    private ?string $region;

    private function __construct(int $id, string $name, string $platform, ?string $region)
    {
        $this->id = $id;
        $this->name = $name;
        $this->platform = $platform;
        $this->region = $region;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $region = $row['region'] ?? null;
        if ($region !== null) {
            $region = trim((string) $region);
            if ($region === '') {
                $region = null;
            }
        }

        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['name'] ?? ''),
            (string) ($row['platform'] ?? ''),
            $region
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

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }
}
