<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PaginationRenderer.php';
require_once __DIR__ . '/TemplateRenderer.php';
require_once __DIR__ . '/Application.php';

class ApplicationContainer
{
    public function __construct(
        private readonly Database $database,
        private readonly Utility $utility,
        private readonly PaginationRenderer $paginationRenderer,
    ) {
    }

    private ?GameRepository $gameRepository = null;

    private ?TrophyRepository $trophyRepository = null;

    private ?PlayerRepository $playerRepository = null;

    private ?Router $router = null;

    private ?TemplateRenderer $templateRenderer = null;

    #[\NoDiscard]
    public static function create(): self
    {
        return new self(
            Database::fromEnvironment($_ENV ?? []),
            new Utility(),
            new PaginationRenderer()
        );
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
        return $this->gameRepository ??= new GameRepository($this->database);
    }

    public function getTrophyRepository(): TrophyRepository
    {
        return $this->trophyRepository ??= new TrophyRepository($this->database);
    }

    public function getPlayerRepository(): PlayerRepository
    {
        return $this->playerRepository ??= new PlayerRepository($this->database);
    }

    public function getRouter(): Router
    {
        return $this->router ??= new Router(
            $this->getGameRepository(),
            $this->getTrophyRepository(),
            $this->getPlayerRepository()
        );
    }

    public function getTemplateRenderer(): TemplateRenderer
    {
        return $this->templateRenderer ??= new TemplateRenderer(
            $this->getDatabase(),
            $this->getUtility(),
            $this->getPaginationRenderer()
        );
    }

    #[\NoDiscard]
    public function createApplication(HttpRequest $request): Application
    {
        return new Application(
            $this->getRouter(),
            $request,
            $this->getTemplateRenderer()
        );
    }

    #[\NoDiscard]
    public function createRequestFromGlobals(): HttpRequest
    {
        return HttpRequest::fromGlobals();
    }
}
