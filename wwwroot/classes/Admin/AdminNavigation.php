<?php

declare(strict_types=1);

final readonly class AdminNavigationItem
{
    public function __construct(
        final private string $label,
        final private string $href,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHref(): string
    {
        return $this->href;
    }
}

final readonly class AdminNavigation
{
    /**
     * @var list<AdminNavigationItem>
     */
    private array $items;

    /**
     * @param list<AdminNavigationItem> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items === [] ? self::createDefaultItems() : $items;
    }

    /**
     * @return list<AdminNavigationItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return list<AdminNavigationItem>
     */
    private static function createDefaultItems(): array
    {
        return [
            new AdminNavigationItem('Cheater', '/admin/cheater.php'),
            new AdminNavigationItem('Copy group and trophy data', '/admin/copy.php'),
            new AdminNavigationItem('Delete Player', '/admin/delete-player.php'),
            new AdminNavigationItem('Game Details', '/admin/detail.php'),
            new AdminNavigationItem('Game Merge', '/admin/merge.php'),
            new AdminNavigationItem('Logs', '/admin/log.php'),
            new AdminNavigationItem('Possible Cheaters', '/admin/possible.php'),
            new AdminNavigationItem('PSN Game Lookup', '/admin/psn-game-lookup.php'),
            new AdminNavigationItem('PSN Player Lookup', '/admin/psn-player-lookup.php'),
            new AdminNavigationItem('PSN Trophy Title Compare', '/admin/psn-trophy-title-compare.php'),
            new AdminNavigationItem('PSNP+', '/admin/psnp-plus.php'),
            new AdminNavigationItem('Reported Players', '/admin/report.php'),
            new AdminNavigationItem('Rescan Game', '/admin/rescan.php'),
            new AdminNavigationItem('Reset Trophy Data or Delete Merged Game', '/admin/reset.php'),
            new AdminNavigationItem('Unobtainable trophy', '/admin/unobtainable.php'),
            new AdminNavigationItem('Workers', '/admin/workers.php'),
        ];
    }
}
