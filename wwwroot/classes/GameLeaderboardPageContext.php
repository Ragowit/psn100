<?php

declare(strict_types=1);

require_once __DIR__ . '/GameLeaderboardPage.php';
require_once __DIR__ . '/GameLeaderboardService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/GameLeaderboardPlayerNotFoundException.php';

final class GameLeaderboardPageContext
{
    private ?GameLeaderboardPage $page;

    private ?string $title;

    private ?string $gameSlug;

    private ?string $redirectLocation;

    private int $redirectStatusCode;

    private function __construct(
        ?GameLeaderboardPage $page,
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

        $leaderboardService = new GameLeaderboardService($database);
        $gameHeaderService = new GameHeaderService($database);

        try {
            $page = GameLeaderboardPage::create(
                $leaderboardService,
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

    private static function createFromPage(GameLeaderboardPage $page, Utility $utility): self
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
            throw new RuntimeException('GameLeaderboardPageContext does not contain a redirect location.');
        }

        return $this->redirectLocation;
    }

    public function getRedirectStatusCode(): int
    {
        if (!$this->shouldRedirect()) {
            throw new RuntimeException('GameLeaderboardPageContext does not contain a redirect status code.');
        }

        return $this->redirectStatusCode;
    }

    public function getPage(): GameLeaderboardPage
    {
        if ($this->page === null) {
            throw new RuntimeException('GameLeaderboardPageContext does not contain a page.');
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

    public function getFilter(): GameLeaderboardFilter
    {
        return $this->getPage()->getFilter();
    }

    public function getTotalPlayers(): int
    {
        return $this->getPage()->getTotalPlayers();
    }

    public function getLimit(): int
    {
        return $this->getPage()->getLimit();
    }

    public function getOffset(): int
    {
        return $this->getPage()->getOffset();
    }

    public function getTotalPagesCount(): int
    {
        return $this->getPage()->getTotalPagesCount();
    }

    /**
     * @return GameLeaderboardRow[]
     */
    public function getRows(): array
    {
        return $this->getPage()->getRows();
    }

    public function getPlayerAccountId(): ?string
    {
        return $this->getPage()->getPlayerAccountId();
    }

    public function getCurrentPage(): int
    {
        return $this->getPage()->getPage();
    }

    public function getTitle(): string
    {
        if ($this->title === null) {
            throw new RuntimeException('GameLeaderboardPageContext does not contain a title.');
        }

        return $this->title;
    }

    public function getGameSlug(): string
    {
        if ($this->gameSlug === null) {
            throw new RuntimeException('GameLeaderboardPageContext does not contain a game slug.');
        }

        return $this->gameSlug;
    }
}
