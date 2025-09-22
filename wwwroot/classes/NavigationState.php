<?php

declare(strict_types=1);

final class NavigationState
{
    private const ACTIVE_SUFFIX = ' active';
    private const NAVIGATION_KEYS = ['home', 'leaderboard', 'game', 'trophy', 'avatar', 'about'];

    private string $sort;
    private string $player;
    private string $filter;
    private string $search;

    /** @var array<string, string> */
    private array $activeClasses;

    private function __construct(string $requestUri, array $queryParameters)
    {
        $this->sort = $this->sanitizeQueryValue($queryParameters['sort'] ?? '');
        $this->player = $this->sanitizeQueryValue($queryParameters['player'] ?? '');
        $this->filter = $this->sanitizeQueryValue($queryParameters['filter'] ?? '');
        $this->search = $this->sanitizeQueryValue($queryParameters['search'] ?? '');

        $this->activeClasses = $this->determineActiveClasses($requestUri);
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

    private function getActiveClass(string $section): string
    {
        return $this->activeClasses[$section] ?? '';
    }

    /**
     * @param string $requestUri
     * @return array<string, string>
     */
    private function determineActiveClasses(string $requestUri): array
    {
        $classes = array_fill_keys(self::NAVIGATION_KEYS, '');

        if (str_starts_with($requestUri, '/leaderboard') || str_starts_with($requestUri, '/player')) {
            $classes['leaderboard'] = self::ACTIVE_SUFFIX;
        } elseif (str_starts_with($requestUri, '/game')) {
            $classes['game'] = self::ACTIVE_SUFFIX;
        } elseif (str_starts_with($requestUri, '/trophy')) {
            $classes['trophy'] = self::ACTIVE_SUFFIX;
        } elseif (str_starts_with($requestUri, '/avatar')) {
            $classes['avatar'] = self::ACTIVE_SUFFIX;
        } elseif (str_starts_with($requestUri, '/about')) {
            $classes['about'] = self::ACTIVE_SUFFIX;
        } else {
            $classes['home'] = self::ACTIVE_SUFFIX;
        }

        return $classes;
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
}
