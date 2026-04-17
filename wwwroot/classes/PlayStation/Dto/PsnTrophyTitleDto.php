<?php

declare(strict_types=1);

final class PsnTrophyTitleDto
{
    /**
     * @param array<int, string> $platforms
     */
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly string $name,
        private readonly string $detail,
        private readonly string $iconUrl,
        private readonly string $lastUpdatedDateTime,
        private readonly string $trophySetVersion,
        private readonly array $platforms
    ) {
    }

    public function npCommunicationId(): string { return $this->npCommunicationId; }
    public function name(): string { return $this->name; }
    public function detail(): string { return $this->detail; }
    public function iconUrl(): string { return $this->iconUrl; }
    public function lastUpdatedDateTime(): string { return $this->lastUpdatedDateTime; }
    public function trophySetVersion(): string { return $this->trophySetVersion; }

    /**
     * @return array<int, string>
     */
    public function platforms(): array
    {
        return $this->platforms;
    }
}
