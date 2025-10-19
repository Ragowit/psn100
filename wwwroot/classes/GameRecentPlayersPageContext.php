<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRecentPlayersPage.php';
require_once __DIR__ . '/GameRecentPlayersService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/GameLeaderboardPlayerNotFoundException.php';

final class GameRecentPlayersPageContext
{
    private ?GameRecentPlayersPage $page;

    private ?string $title;

    private ?string $gameSlug;

    private ?string $redirectLocation;

    private int $redirectStatusCode;

    private function __construct(
        ?GameRecentPlayersPage $page,
        ?string $title,
        ?string $gameSlug,
        ?string $redirectLocation,
        int $redirectStatusCode
    ) {
        $this->page = $page;
        $this->title = $title;
        $this->gameSlug = $gameSlug;
        $this->redirectLocation = $redirectLocation;
        $this->redirectStatusCode = $redirectStatusCode;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromGlobals(
        PDO $database,
        Utility $utility,
        ?int $gameId,
        ?string $player,
        array $queryParameters
    ): self {
        if ($gameId === null) {
            return self::createRedirect('/game/');
        }

        $recentPlayersService = new GameRecentPlayersService($database);
        $gameHeaderService = new GameHeaderService($database);

        try {
            $page = GameRecentPlayersPage::create(
                $recentPlayersService,
                $gameHeaderService,
                $gameId,
                $player,
                $queryParameters
            );

            return self::createFromPage($page, $utility);
        } catch (GameNotFoundException $exception) {
            return self::createRedirect('/game/');
        } catch (GameLeaderboardPlayerNotFoundException $exception) {
            $redirectSlug = $utility->slugify($exception->getGameName());
            $redirectPath = '/game/' . $exception->getGameId() . '-' . $redirectSlug;

            return self::createRedirect($redirectPath);
        }
    }

    private static function createFromPage(GameRecentPlayersPage $page, Utility $utility): self
    {
        return new self(
            $page,
            $page->getPageTitle(),
            $page->getGameSlug($utility),
            null,
            0
        );
    }

    private static function createRedirect(string $location, int $statusCode = 303): self
    {
        return new self(null, null, null, $location, $statusCode);
    }

    public function shouldRedirect(): bool
    {
        return $this->redirectLocation !== null;
    }

    public function getRedirectLocation(): string
    {
        if ($this->redirectLocation === null) {
            throw new RuntimeException('GameRecentPlayersPageContext does not contain a redirect location.');
        }

        return $this->redirectLocation;
    }

    public function getRedirectStatusCode(): int
    {
        if (!$this->shouldRedirect()) {
            throw new RuntimeException('GameRecentPlayersPageContext does not contain a redirect status code.');
        }

        return $this->redirectStatusCode;
    }

    public function getPage(): GameRecentPlayersPage
    {
        if ($this->page === null) {
            throw new RuntimeException('GameRecentPlayersPageContext does not contain a page.');
        }

        return $this->page;
    }

    public function getGame(): GameDetails
    {
        return $this->getPage()->getGame();
    }

    public function getGameHeaderData(): GameHeaderData
    {
        return $this->getPage()->getGameHeaderData();
    }

    public function getFilter(): GamePlayerFilter
    {
        return $this->getPage()->getFilter();
    }

    /**
     * @return GameRecentPlayer[]
     */
    public function getRecentPlayers(): array
    {
        return $this->getPage()->getRecentPlayers();
    }

    public function getPlayerAccountId(): ?string
    {
        return $this->getPage()->getPlayerAccountId();
    }

    public function getGamePlayer(): ?GamePlayerProgress
    {
        return $this->getPage()->getGamePlayer();
    }

    public function getTitle(): string
    {
        if ($this->title === null) {
            throw new RuntimeException('GameRecentPlayersPageContext does not contain a title.');
        }

        return $this->title;
    }

    public function getGameSlug(): string
    {
        if ($this->gameSlug === null) {
            throw new RuntimeException('GameRecentPlayersPageContext does not contain a game slug.');
        }

        return $this->gameSlug;
    }
}
