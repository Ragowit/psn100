<?php

declare(strict_types=1);

readonly class HomepageNewGame extends HomepageTitle
{
    private function __construct(
        int $id,
        string $name,
        string $iconUrl,
        string $platform,
        private int $platinum,
        private int $gold,
        private int $silver,
        private int $bronze,
    ) {
        parent::__construct($id, $name, $iconUrl, $platform, 'title');
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            (int) ($row['platinum'] ?? 0),
            (int) ($row['gold'] ?? 0),
            (int) ($row['silver'] ?? 0),
            (int) ($row['bronze'] ?? 0)
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
