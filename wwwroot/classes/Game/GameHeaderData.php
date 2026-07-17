<?php

declare(strict_types=1);

final readonly class GameHeaderData
{
    /**
     * @param GameHeaderStack[] $stacks
     * @param GameObsoleteReplacement[] $obsoleteReplacements
     */
    public function __construct(
        private ?GameHeaderParent $parentGame,
        private array $stacks,
        private int $unobtainableTrophyCount,
        private array $obsoleteReplacements,
        private ?string $psnpPlusNote,
    ) {
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

    /**
     * @return GameObsoleteReplacement[]
     */
    public function getObsoleteReplacements(): array
    {
        return $this->obsoleteReplacements;
    }

    public function hasObsoleteReplacements(): bool
    {
        return $this->obsoleteReplacements !== [];
    }

    public function getPsnpPlusNote(): ?string
    {
        return $this->psnpPlusNote;
    }

    public function hasPsnpPlusNote(): bool
    {
        return $this->psnpPlusNote !== null;
    }
}
