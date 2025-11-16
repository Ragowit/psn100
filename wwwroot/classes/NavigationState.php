<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationSectionState.php';

final class NavigationState
{
    private const NAVIGATION_KEYS = ['home', 'leaderboard', 'game', 'trophy', 'avatar', 'about'];

    private string $sort;
    private string $player;
    private string $filter;
    private string $search;

    /** @var array<string, NavigationSectionState> */
    private array $sectionStates;

    private function __construct(string $requestUri, array $queryParameters)
    {
        $this->sort = $this->sanitizeQueryValue($queryParameters['sort'] ?? '');
        $this->player = $this->sanitizeQueryValue($queryParameters['player'] ?? '');
        $this->filter = $this->sanitizeQueryValue($queryParameters['filter'] ?? '');
        $this->search = $this->sanitizeQueryValue($queryParameters['search'] ?? '');

        $this->sectionStates = $this->determineSectionStates($requestUri);
    }

    public static function fromGlobals(array $server, array $queryParameters): self
    {
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');

        return new self($requestUri, $queryParameters);
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function getPlayer(): string
    {
        return $this->player;
    }

    public function getFilter(): string
    {
        return $this->filter;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function getHomeClass(): string
    {
        return $this->getActiveClass('home');
    }

    public function getLeaderboardClass(): string
    {
        return $this->getActiveClass('leaderboard');
    }

    public function getGameClass(): string
    {
        return $this->getActiveClass('game');
    }

    public function getTrophyClass(): string
    {
        return $this->getActiveClass('trophy');
    }

    public function getAvatarClass(): string
    {
        return $this->getActiveClass('avatar');
    }

    public function getAboutClass(): string
    {
        return $this->getActiveClass('about');
    }

    public function isSectionActive(string $section): bool
    {
        return $this->getSectionState($section)?->isActive() ?? false;
    }

    private function getActiveClass(string $section): string
    {
        $state = $this->getSectionState($section);

        return $state?->getCssClass() ?? '';
    }

    /**
     * @param string $requestUri
     * @return array<string, NavigationSectionState>
     */
    private function determineSectionStates(string $requestUri): array
    {
        $states = [];

        foreach (self::NAVIGATION_KEYS as $section) {
            $states[$section] = new NavigationSectionState($section, false);
        }

        $activeSection = $this->resolveActiveSection($requestUri);
        $states[$activeSection] = new NavigationSectionState($activeSection, true);

        return $states;
    }

    private function sanitizeQueryValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
            if ($value === false) {
                $value = '';
            }
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function resolveActiveSection(string $requestUri): string
    {
        if (str_starts_with($requestUri, '/leaderboard') || str_starts_with($requestUri, '/player')) {
            return 'leaderboard';
        }

        if (str_starts_with($requestUri, '/game')) {
            return 'game';
        }

        if (str_starts_with($requestUri, '/trophy')) {
            return 'trophy';
        }

        if (str_starts_with($requestUri, '/avatar')) {
            return 'avatar';
        }

        if (str_starts_with($requestUri, '/about')) {
            return 'about';
        }

        return 'home';
    }

    private function getSectionState(string $section): ?NavigationSectionState
    {
        return $this->sectionStates[$section] ?? null;
    }
}
