<?php

declare(strict_types=1);

final class PossibleCheaterSectionDefinition
{
    public function __construct(
        private string $title,
        private string $query,
        private string $linkPattern
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
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
