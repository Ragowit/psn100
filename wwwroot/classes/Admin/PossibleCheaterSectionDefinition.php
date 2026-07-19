<?php

declare(strict_types=1);

final readonly class PossibleCheaterSectionDefinition
{
    public function __construct(
        final private string $title,
        final private string $query,
        final private string $linkPattern
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['title'] ?? ''),
            (string) ($data['query'] ?? ''),
            (string) ($data['linkPattern'] ?? '')
        );
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function buildLink(string $onlineId): string
    {
        return sprintf($this->linkPattern, rawurlencode($onlineId));
    }
}
