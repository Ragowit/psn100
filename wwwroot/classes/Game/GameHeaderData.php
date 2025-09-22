<?php

declare(strict_types=1);

class GameHeaderData
{
    private ?GameHeaderParent $parentGame;

    /**
     * @var GameHeaderStack[]
     */
    private array $stacks;

    private int $unobtainableTrophyCount;

    /**
     * @param GameHeaderStack[] $stacks
     */
    public function __construct(?GameHeaderParent $parentGame, array $stacks, int $unobtainableTrophyCount)
    {
        $this->parentGame = $parentGame;
        $this->stacks = $stacks;
        $this->unobtainableTrophyCount = $unobtainableTrophyCount;
    }

    public function hasMergedParent(): bool
    {
        return $this->parentGame !== null;
    }

    public function getParentGame(): ?GameHeaderParent
    {
        return $this->parentGame;
    }

    /**
     * @return GameHeaderStack[]
     */
    public function getStacks(): array
    {
        return $this->stacks;
    }

    public function hasStacks(): bool
    {
        return $this->stacks !== [];
    }

    public function getUnobtainableTrophyCount(): int
    {
        return $this->unobtainableTrophyCount;
    }

    public function hasUnobtainableTrophies(): bool
    {
        return $this->unobtainableTrophyCount > 0;
    }
}
