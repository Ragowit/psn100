<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerNavigationSection.php';

final readonly class PlayerNavigation
{
    private function __construct(
        private string $onlineId,
        private ?PlayerNavigationSection $activeSection,
    ) {
    }

    public static function forSection(string $onlineId, ?PlayerNavigationSection $activeSection = null): self
    {
        return new self($onlineId, $activeSection);
    }

    /**
     * @return PlayerNavigationLink[]
     */
    public function getLinks(): array
    {
        $encodedOnlineId = rawurlencode($this->onlineId);

        return [
            new PlayerNavigationLink(
                'Games',
                '/player/' . $encodedOnlineId,
                $this->isActive(PlayerNavigationSection::GAMES)
            ),
            new PlayerNavigationLink(
                'Log',
                '/player/' . $encodedOnlineId . '/log',
                $this->isActive(PlayerNavigationSection::LOG)
            ),
            new PlayerNavigationLink(
                'Trophy Advisor',
                '/player/' . $encodedOnlineId . '/advisor',
                $this->isActive(PlayerNavigationSection::TROPHY_ADVISOR)
            ),
            new PlayerNavigationLink(
                'Game Advisor',
                '/game?sort=completion&filter=true&player=' . $encodedOnlineId,
                $this->isActive(PlayerNavigationSection::GAME_ADVISOR)
            ),
            new PlayerNavigationLink(
                'Random Games',
                '/player/' . $encodedOnlineId . '/random',
                $this->isActive(PlayerNavigationSection::RANDOM)
            ),
        ];
    }

    private function isActive(PlayerNavigationSection $section): bool
    {
        return $this->activeSection === $section;
    }
}

final readonly class PlayerNavigationLink
{
    public function __construct(
        private string $label,
        private string $url,
        private bool $active,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getButtonCssClass(): string
    {
        return $this->active
            ? 'btn btn-primary active'
            : 'btn btn-outline-primary';
    }

    public function getAriaCurrent(): ?string
    {
        return $this->active ? 'page' : null;
    }
}
