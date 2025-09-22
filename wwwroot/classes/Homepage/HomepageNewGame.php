<?php

declare(strict_types=1);

class HomepageNewGame extends HomepageTitle
{
    private int $platinum;

    private int $gold;

    private int $silver;

    private int $bronze;

    private function __construct(
        int $id,
        string $name,
        string $iconUrl,
        string $platform,
        int $platinum,
        int $gold,
        int $silver,
        int $bronze
    ) {
        parent::__construct($id, $name, $iconUrl, $platform, 'title');
        $this->platinum = $platinum;
        $this->gold = $gold;
        $this->silver = $silver;
        $this->bronze = $bronze;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : 0,
            (string) ($row['name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            isset($row['platinum']) ? (int) $row['platinum'] : 0,
            isset($row['gold']) ? (int) $row['gold'] : 0,
            isset($row['silver']) ? (int) $row['silver'] : 0,
            isset($row['bronze']) ? (int) $row['bronze'] : 0
        );
    }

    public function getPlatinum(): int
    {
        return $this->platinum;
    }

    public function getGold(): int
    {
        return $this->gold;
    }

    public function getSilver(): int
    {
        return $this->silver;
    }

    public function getBronze(): int
    {
        return $this->bronze;
    }
}
