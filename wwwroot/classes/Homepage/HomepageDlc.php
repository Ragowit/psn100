<?php

declare(strict_types=1);

require_once __DIR__ . '/../HistoryIconType.php';

final readonly class HomepageDlc extends HomepageTitle
{
    private function __construct(
        int $id,
        string $gameName,
        final private string $groupId,
        final private string $groupName,
        string $iconUrl,
        string $platform,
        final private int $gold,
        final private int $silver,
        final private int $bronze,
    ) {
        parent::__construct($id, $gameName, $iconUrl, $platform, HistoryIconType::Group);
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            gameName: (string) ($row['game_name'] ?? ''),
            groupId: (string) ($row['group_id'] ?? ''),
            groupName: (string) ($row['group_name'] ?? ''),
            iconUrl: (string) ($row['icon_url'] ?? ''),
            platform: (string) ($row['platform'] ?? ''),
            gold: (int) ($row['gold'] ?? 0),
            silver: (int) ($row['silver'] ?? 0),
            bronze: (int) ($row['bronze'] ?? 0),
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

    #[\Override]
    public function getRelativeUrl(Utility $utility): string
    {
        $path = parent::getRelativeUrl($utility);

        return Uri\Rfc3986\Uri::parse($path)
            ?->withFragment($this->groupId)
            ->toRawString()
            ?? $path . '#' . $this->groupId;
    }
}
