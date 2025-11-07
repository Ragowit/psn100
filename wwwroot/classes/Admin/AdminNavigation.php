<?php

declare(strict_types=1);

class AdminNavigationItem
{
    private string $label;

    private string $href;

    public function __construct(string $label, string $href)
    {
        $this->label = $label;
        $this->href = $href;
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

class AdminNavigation
{
    /**
     * @var AdminNavigationItem[]
     */
    private array $items;

    /**
     * @param AdminNavigationItem[] $items
     */
    public function __construct(array $items = [])
    {
        if ($items === []) {
            $items = $this->createDefaultItems();
        }

        $this->items = $items;
    }

    public function addItem(AdminNavigationItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return AdminNavigationItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return AdminNavigationItem[]
     */
    private function createDefaultItems(): array
    {
        return [
            new AdminNavigationItem('Cheater', '/admin/cheater.php'),
            new AdminNavigationItem('Copy group and trophy data', '/admin/copy.php'),
            new AdminNavigationItem('Delete Player', '/admin/delete-player.php'),
            new AdminNavigationItem('Game Details', '/admin/detail.php'),
            new AdminNavigationItem('Game Merge', '/admin/merge.php'),
            new AdminNavigationItem('Logs', '/admin/log.php'),
            new AdminNavigationItem('Possible Cheaters', '/admin/possible.php'),
            new AdminNavigationItem('PSN Player Search', '/admin/psn-player-search.php'),
            new AdminNavigationItem('PSNP+', '/admin/psnp-plus.php'),
            new AdminNavigationItem('Reported Players', '/admin/report.php'),
            new AdminNavigationItem('Rescan Game', '/admin/rescan.php'),
            new AdminNavigationItem('Reset Trophy Data or Delete Merged Game', '/admin/reset.php'),
            new AdminNavigationItem('Unobtainable trophy', '/admin/unobtainable.php'),
            new AdminNavigationItem('Workers', '/admin/workers.php'),
        ];
    }
}
