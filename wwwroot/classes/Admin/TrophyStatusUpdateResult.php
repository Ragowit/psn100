<?php

declare(strict_types=1);

final readonly class TrophyStatusUpdateResult
{
    /**
     * @param string[] $trophyNames
     */
    public function __construct(
        final private array $trophyNames,
        final private string $statusText,
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
