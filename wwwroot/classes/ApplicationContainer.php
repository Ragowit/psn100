<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PaginationRenderer.php';
require_once __DIR__ . '/Application.php';

class ApplicationContainer
{
    private Database $database;

    private Utility $utility;

    private PaginationRenderer $paginationRenderer;

    private ?GameRepository $gameRepository = null;

    private ?TrophyRepository $trophyRepository = null;

    private ?PlayerRepository $playerRepository = null;

    private ?Router $router = null;

    public function __construct(
        ?Database $database = null,
        ?Utility $utility = null,
        ?PaginationRenderer $paginationRenderer = null
    ) {
        $this->database = $database ?? new Database();
        $this->utility = $utility ?? new Utility();
        $this->paginationRenderer = $paginationRenderer ?? new PaginationRenderer();
    }

    public static function create(): self
    {
        return new self();
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getUtility(): Utility
    {
        return $this->utility;
    }

    public function getPaginationRenderer(): PaginationRenderer
    {
        return $this->paginationRenderer;
    }

    public function getGameRepository(): GameRepository
    {
        if ($this->gameRepository === null) {
            $this->gameRepository = new GameRepository($this->database);
        }

        return $this->gameRepository;
    }

    public function getTrophyRepository(): TrophyRepository
    {
        if ($this->trophyRepository === null) {
            $this->trophyRepository = new TrophyRepository($this->database);
        }

        return $this->trophyRepository;
    }

    public function getPlayerRepository(): PlayerRepository
    {
        if ($this->playerRepository === null) {
            $this->playerRepository = new PlayerRepository($this->database);
        }

        return $this->playerRepository;
    }

    public function getRouter(): Router
    {
        if ($this->router === null) {
            $this->router = new Router(
                $this->getGameRepository(),
                $this->getTrophyRepository(),
                $this->getPlayerRepository()
            );
        }

        return $this->router;
    }

    public function createApplication(HttpRequest $request): Application
    {
        return new Application($this->getRouter(), $request);
    }

    public function createRequestFromGlobals(): HttpRequest
    {
        return HttpRequest::fromGlobals();
    }
}
