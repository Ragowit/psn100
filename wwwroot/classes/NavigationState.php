<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationSection.php';
require_once __DIR__ . '/NavigationSectionState.php';
require_once __DIR__ . '/RequestParameter.php';
require_once __DIR__ . '/Html.php';

final readonly class NavigationState
{
    /**
     * @param array<string, NavigationSectionState> $sectionStates
     */
    private function __construct(
        final private string $sort,
        final private string $player,
        final private string $filter,
        final private string $search,
        final private array $sectionStates,
    ) {
    }

    #[\NoDiscard]
    public static function fromGlobals(array $server, array $queryParameters): self
    {
        $requestPath = Uri\Rfc3986\Uri::parse((string) ($server['REQUEST_URI'] ?? '/'))?->getPath() ?? '/';

        $sort = self::sanitizeQueryValue($queryParameters['sort'] ?? '');
        $player = self::sanitizeQueryValue($queryParameters['player'] ?? '');
        $filter = self::sanitizeQueryValue($queryParameters['filter'] ?? '');
        $search = self::sanitizeQueryValue($queryParameters['search'] ?? '');

        return new self(
            $sort,
            $player,
            $filter,
            $search,
            self::determineSectionStates($requestPath)
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
     * @param string $requestPath
     * @return array<string, NavigationSectionState>
     */
    private static function determineSectionStates(string $requestPath): array
    {
        $states = [];

        foreach (NavigationSection::cases() as $section) {
            $states[$section->value] = new NavigationSectionState($section, false);
        }

        $activeSection = self::resolveActiveSection($requestPath);
        $states[$activeSection->value] = new NavigationSectionState($activeSection, true);

        return $states;
    }

    private static function sanitizeQueryValue(mixed $value): string
    {
        $value = RequestParameter::firstScalar($value) ?? '';

        return Html::escape((string) $value);
    }

    private static function resolveActiveSection(string $requestPath): NavigationSection
    {
        return match (true) {
            str_starts_with($requestPath, '/leaderboard') || str_starts_with($requestPath, '/player')
                => NavigationSection::Leaderboard,
            str_starts_with($requestPath, '/game') => NavigationSection::Game,
            str_starts_with($requestPath, '/trophy') => NavigationSection::Trophy,
            str_starts_with($requestPath, '/avatar') => NavigationSection::Avatar,
            str_starts_with($requestPath, '/about') => NavigationSection::About,
            default => NavigationSection::Home,
        };
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
