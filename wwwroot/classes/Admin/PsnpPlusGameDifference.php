<?php

declare(strict_types=1);

class PsnpPlusGameDifference
{
    private int $gameId;
    private string $gameName;
    private string $npCommunicationId;
    private int $psnprofilesId;
    /**
     * @var int[]
     */
    private array $unobtainableOrders;
    /**
     * @var int[]
     */
    private array $unobtainableTrophyIds;
    /**
     * @var int[]
     */
    private array $obtainableOrders;
    /**
     * @var int[]
     */
    private array $obtainableTrophyIds;

    /**
     * @param int[] $unobtainableOrders
     * @param int[] $unobtainableTrophyIds
     * @param int[] $obtainableOrders
     * @param int[] $obtainableTrophyIds
     */
    public function __construct(
        int $gameId,
        string $gameName,
        string $npCommunicationId,
        int $psnprofilesId,
        array $unobtainableOrders,
        array $unobtainableTrophyIds,
        array $obtainableOrders,
        array $obtainableTrophyIds
    ) {
        $this->gameId = $gameId;
        $this->gameName = $gameName;
        $this->npCommunicationId = $npCommunicationId;
        $this->psnprofilesId = $psnprofilesId;
        $this->unobtainableOrders = array_map('intval', $unobtainableOrders);
        $this->unobtainableTrophyIds = array_map('intval', $unobtainableTrophyIds);
        $this->obtainableOrders = array_map('intval', $obtainableOrders);
        $this->obtainableTrophyIds = array_map('intval', $obtainableTrophyIds);
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getGameName(): string
    {
        return $this->gameName;
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getPsnprofilesId(): int
    {
        return $this->psnprofilesId;
    }

    /**
     * @return int[]
     */
    public function getUnobtainableOrders(): array
    {
        return $this->unobtainableOrders;
    }

    public function hasUnobtainable(): bool
    {
        return $this->unobtainableOrders !== [];
    }

    public function getUnobtainableOrderList(): string
    {
        return $this->formatList($this->unobtainableOrders);
    }

    /**
     * @return int[]
     */
    public function getUnobtainableTrophyIds(): array
    {
        return $this->unobtainableTrophyIds;
    }

    public function getUnobtainableTrophyIdQuery(): string
    {
        return $this->formatQuery($this->unobtainableTrophyIds);
    }

    /**
     * @return int[]
     */
    public function getObtainableOrders(): array
    {
        return $this->obtainableOrders;
    }

    public function hasObtainable(): bool
    {
        return $this->obtainableOrders !== [];
    }

    public function getObtainableOrderList(): string
    {
        return $this->formatList($this->obtainableOrders);
    }

    /**
     * @return int[]
     */
    public function getObtainableTrophyIds(): array
    {
        return $this->obtainableTrophyIds;
    }

    public function getObtainableTrophyIdQuery(): string
    {
        return $this->formatQuery($this->obtainableTrophyIds);
    }

    /**
     * @param int[] $values
     */
    private function formatList(array $values): string
    {
        return implode(', ', array_map('strval', $values));
    }

    /**
     * @param int[] $values
     */
    private function formatQuery(array $values): string
    {
        return implode(',', array_map('strval', $values));
    }
}
