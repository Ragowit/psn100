<?php

declare(strict_types=1);

readonly class HomepageDlc extends HomepageTitle
{
    private function __construct(
        int $id,
        string $gameName,
        private string $groupId,
        private string $groupName,
        string $iconUrl,
        string $platform,
        private int $gold,
        private int $silver,
        private int $bronze,
    ) {
        parent::__construct($id, $gameName, $iconUrl, $platform, 'group');
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['game_name'] ?? ''),
            (string) ($row['group_id'] ?? ''),
            (string) ($row['group_name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            (int) ($row['gold'] ?? 0),
            (int) ($row['silver'] ?? 0),
            (int) ($row['bronze'] ?? 0)
        );
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getGroupId(): string
    {
        return $this->groupId;
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

    public function getRelativeUrl(Utility $utility): string
    {
        return parent::getRelativeUrl($utility) . '#' . $this->groupId;
    }
}
