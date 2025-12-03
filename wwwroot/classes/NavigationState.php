<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationSection.php';
require_once __DIR__ . '/NavigationSectionState.php';

final class NavigationState
{
    /**
     * @param array<string, NavigationSectionState> $sectionStates
     */
    private function __construct(
        private readonly string $sort,
        private readonly string $player,
        private readonly string $filter,
        private readonly string $search,
        private readonly array $sectionStates,
    ) {
    }

    public static function fromGlobals(array $server, array $queryParameters): self
    {
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');

        $sort = self::sanitizeQueryValue($queryParameters['sort'] ?? '');
        $player = self::sanitizeQueryValue($queryParameters['player'] ?? '');
        $filter = self::sanitizeQueryValue($queryParameters['filter'] ?? '');
        $search = self::sanitizeQueryValue($queryParameters['search'] ?? '');

        return new self(
            $sort,
            $player,
            $filter,
            $search,
            self::determineSectionStates($requestUri)
        );
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
        return $this->getActiveClass(NavigationSection::Home);
    }

    public function getLeaderboardClass(): string
    {
        return $this->getActiveClass(NavigationSection::Leaderboard);
    }

    public function getGameClass(): string
    {
        return $this->getActiveClass(NavigationSection::Game);
    }

    public function getTrophyClass(): string
    {
        return $this->getActiveClass(NavigationSection::Trophy);
    }

    public function getAvatarClass(): string
    {
        return $this->getActiveClass(NavigationSection::Avatar);
    }

    public function getAboutClass(): string
    {
        return $this->getActiveClass(NavigationSection::About);
    }

    public function isSectionActive(NavigationSection|string $section): bool
    {
        $resolvedSection = self::toNavigationSection($section);

        if ($resolvedSection === null) {
            return false;
        }

        return $this->getSectionState($resolvedSection)?->isActive() ?? false;
    }

    private function getActiveClass(NavigationSection|string $section): string
    {
        $resolvedSection = self::toNavigationSection($section);

        if ($resolvedSection === null) {
            return '';
        }

        $state = $this->getSectionState($resolvedSection);

        return $state?->getCssClass() ?? '';
    }

    /**
     * @param string $requestUri
     * @return array<string, NavigationSectionState>
     */
    private static function determineSectionStates(string $requestUri): array
    {
        $states = [];

        foreach (NavigationSection::cases() as $section) {
            $states[$section->value] = new NavigationSectionState($section, false);
        }

        $activeSection = self::resolveActiveSection($requestUri);
        $states[$activeSection->value] = new NavigationSectionState($activeSection, true);

        return $states;
    }

    private static function sanitizeQueryValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
            if ($value === false) {
                $value = '';
            }
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function resolveActiveSection(string $requestUri): NavigationSection
    {
        if (str_starts_with($requestUri, '/leaderboard') || str_starts_with($requestUri, '/player')) {
            return NavigationSection::Leaderboard;
        }

        if (str_starts_with($requestUri, '/game')) {
            return NavigationSection::Game;
        }

        if (str_starts_with($requestUri, '/trophy')) {
            return NavigationSection::Trophy;
        }

        if (str_starts_with($requestUri, '/avatar')) {
            return NavigationSection::Avatar;
        }

        if (str_starts_with($requestUri, '/about')) {
            return NavigationSection::About;
        }

        return NavigationSection::Home;
    }

    private function getSectionState(NavigationSection $section): ?NavigationSectionState
    {
        return $this->sectionStates[$section->value] ?? null;
    }

    private static function toNavigationSection(NavigationSection|string $section): ?NavigationSection
    {
        if ($section instanceof NavigationSection) {
            return $section;
        }

        return NavigationSection::fromName($section);
    }
}
