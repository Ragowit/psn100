<?php

declare(strict_types=1);

class HomepageDlc extends HomepageTitle
{
    private string $groupId;

    private string $groupName;

    private int $gold;

    private int $silver;

    private int $bronze;

    private function __construct(
        int $id,
        string $gameName,
        string $groupId,
        string $groupName,
        string $iconUrl,
        string $platform,
        int $gold,
        int $silver,
        int $bronze
    ) {
        parent::__construct($id, $gameName, $iconUrl, $platform, 'group');
        $this->groupId = $groupId;
        $this->groupName = $groupName;
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
            (string) ($row['game_name'] ?? ''),
            (string) ($row['group_id'] ?? ''),
            (string) ($row['group_name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            isset($row['gold']) ? (int) $row['gold'] : 0,
            isset($row['silver']) ? (int) $row['silver'] : 0,
            isset($row['bronze']) ? (int) $row['bronze'] : 0
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
