<?php

declare(strict_types=1);

final readonly class TrophyStatusUpdateResult
{
    /**
     * @param string[] $trophyNames
     */
    public function __construct(
        private array $trophyNames,
        private string $statusText,
    ) {
    }

    /**
     * @return string[]
     */
    public function getTrophyNames(): array
    {
        return $this->trophyNames;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }
}
