<?php

declare(strict_types=1);

readonly class PsnpPlusFixedGame
{
    /**
     * @var int[]
     */
    private array $trophyIds;

    public function __construct(
        private int $gameId,
        private string $gameName,
        array $trophyIds
    ) {
        $this->trophyIds = array_map('intval', $trophyIds);
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getGameName(): string
    {
        return $this->gameName;
    }

    /**
     * @return int[]
     */
    public function getTrophyIds(): array
    {
        return $this->trophyIds;
    }

    public function hasTrophies(): bool
    {
        return $this->trophyIds !== [];
    }

    public function getTrophyIdList(): string
    {
        return $this->formatList($this->trophyIds);
    }

    public function getTrophyIdQuery(): string
    {
        return implode(',', array_map('strval', $this->trophyIds));
    }

    /**
     * @param int[] $values
     */
    private function formatList(array $values): string
    {
        return implode(', ', array_map('strval', $values));
    }
}
