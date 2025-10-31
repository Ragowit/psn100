<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/ApplicationContainer.php';
require_once __DIR__ . '/../wwwroot/classes/GameRepository.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyRepository.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRepository.php';
require_once __DIR__ . '/../wwwroot/classes/Router.php';
require_once __DIR__ . '/../wwwroot/classes/TemplateRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/Application.php';
require_once __DIR__ . '/../wwwroot/classes/RouteResultResponder.php';
require_once __DIR__ . '/../wwwroot/classes/HttpRequest.php';

final class ApplicationContainerTestDatabase extends Database
{
    public function __construct()
    {
        // Intentionally bypass the parent constructor to avoid opening a real
        // database connection during tests.
    }
}

final class ApplicationContainerTest extends TestCase
{
    private ApplicationContainerTestDatabase $database;

    private Utility $utility;

    private PaginationRenderer $paginationRenderer;

    private ApplicationContainer $container;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $originalServer = null;

    protected function setUp(): void
    {
        $this->originalServer = isset($_SERVER) ? $_SERVER : null;

        $this->database = new ApplicationContainerTestDatabase();
        $this->utility = new Utility();
        $this->paginationRenderer = new PaginationRenderer();

        $this->container = new ApplicationContainer(
            $this->database,
            $this->utility,
            $this->paginationRenderer
        );
    }

    protected function tearDown(): void
    {
        if ($this->originalServer === null) {
            unset($_SERVER);
        } else {
            $_SERVER = $this->originalServer;
        }
    }

    public function testGettersReturnInjectedDependencies(): void
    {
        $this->assertSame($this->database, $this->container->getDatabase());
        $this->assertSame($this->utility, $this->container->getUtility());
        $this->assertSame($this->paginationRenderer, $this->container->getPaginationRenderer());
    }

    public function testRepositoryAccessReturnsMemoizedInstances(): void
    {
        $gameRepository = $this->container->getGameRepository();
        $trophyRepository = $this->container->getTrophyRepository();
        $playerRepository = $this->container->getPlayerRepository();

        $this->assertSame($gameRepository, $this->container->getGameRepository());
        $this->assertSame($trophyRepository, $this->container->getTrophyRepository());
        $this->assertSame($playerRepository, $this->container->getPlayerRepository());

        $this->assertRepositoryUsesDatabase(GameRepository::class, $gameRepository);
        $this->assertRepositoryUsesDatabase(TrophyRepository::class, $trophyRepository);
        $this->assertRepositoryUsesDatabase(PlayerRepository::class, $playerRepository);
    }

    public function testGetRouterReturnsSameInstanceWithConfiguredHandlers(): void
    {
        $router = $this->container->getRouter();

        $this->assertSame($router, $this->container->getRouter());

        $routeHandlersProperty = new ReflectionProperty(Router::class, 'routeHandlers');
        $routeHandlersProperty->setAccessible(true);

        /** @var array<string, object> $routeHandlers */
        $routeHandlers = $routeHandlersProperty->getValue($router);

        $this->assertHandlerUsesRepository($routeHandlers, 'game', GameRouteHandler::class, 'gameRepository', $this->container->getGameRepository());
        $this->assertHandlerUsesRepository($routeHandlers, 'game-history', GameRouteHandler::class, 'gameRepository', $this->container->getGameRepository());
        $this->assertHandlerUsesRepository($routeHandlers, 'player', PlayerRouteHandler::class, 'playerRepository', $this->container->getPlayerRepository());
        $this->assertHandlerUsesRepository($routeHandlers, 'trophy', TrophyRouteHandler::class, 'trophyRepository', $this->container->getTrophyRepository());
    }

    public function testGetTemplateRendererReturnsMemoizedInstanceWithDependencies(): void
    {
        $templateRenderer = $this->container->getTemplateRenderer();

        $this->assertSame($templateRenderer, $this->container->getTemplateRenderer());

        $databaseProperty = new ReflectionProperty(TemplateRenderer::class, 'database');
        $databaseProperty->setAccessible(true);
        $this->assertSame($this->database, $databaseProperty->getValue($templateRenderer));

        $utilityProperty = new ReflectionProperty(TemplateRenderer::class, 'utility');
        $utilityProperty->setAccessible(true);
        $this->assertSame($this->utility, $utilityProperty->getValue($templateRenderer));

        $paginationRendererProperty = new ReflectionProperty(TemplateRenderer::class, 'paginationRenderer');
        $paginationRendererProperty->setAccessible(true);
        $this->assertSame($this->paginationRenderer, $paginationRendererProperty->getValue($templateRenderer));
    }

    public function testCreateApplicationSharesRouterTemplateRendererAndRequest(): void
    {
        $request = new HttpRequest(['REQUEST_URI' => '/application-test']);

        $application = $this->container->createApplication($request);

        $this->assertTrue($application instanceof Application);

        $routerProperty = new ReflectionProperty(Application::class, 'router');
        $routerProperty->setAccessible(true);
        $this->assertSame($this->container->getRouter(), $routerProperty->getValue($application));

        $requestProperty = new ReflectionProperty(Application::class, 'request');
        $requestProperty->setAccessible(true);
        $this->assertSame($request, $requestProperty->getValue($application));

        $responderProperty = new ReflectionProperty(Application::class, 'routeResultResponder');
        $responderProperty->setAccessible(true);
        $routeResultResponder = $responderProperty->getValue($application);

        $this->assertTrue($routeResultResponder instanceof RouteResultResponder);

        $templateRendererProperty = new ReflectionProperty(RouteResultResponder::class, 'templateRenderer');
        $templateRendererProperty->setAccessible(true);
        $this->assertSame($this->container->getTemplateRenderer(), $templateRendererProperty->getValue($routeResultResponder));
    }

    public function testCreateRequestFromGlobalsUsesServerSuperglobal(): void
    {
        $_SERVER = [
            'SCRIPT_URL' => '  /from-script  ',
            'REQUEST_URI' => '/from-request',
        ];

        $request = $this->container->createRequestFromGlobals();

        $this->assertTrue($request instanceof HttpRequest);
        $this->assertSame('/from-script', $request->getResolvedUri());
    }

    /**
     * @template T of object
     * @param class-string<T> $expectedClass
     * @param T $repository
     */
    private function assertRepositoryUsesDatabase(string $expectedClass, object $repository): void
    {
        $this->assertTrue($repository instanceof $expectedClass);

        $databaseProperty = new ReflectionProperty($expectedClass, 'database');
        $databaseProperty->setAccessible(true);

        $this->assertSame($this->database, $databaseProperty->getValue($repository));
    }

    /**
     * @param array<string, object> $handlers
     * @param class-string $expectedHandlerClass
     */
    private function assertHandlerUsesRepository(
        array $handlers,
        string $key,
        string $expectedHandlerClass,
        string $repositoryProperty,
        object $expectedRepository
    ): void {
        $this->assertTrue(isset($handlers[$key]));
        $handler = $handlers[$key];
        $this->assertTrue($handler instanceof $expectedHandlerClass);

        $property = new ReflectionProperty($expectedHandlerClass, $repositoryProperty);
        $property->setAccessible(true);

        $this->assertSame($expectedRepository, $property->getValue($handler));
    }
}
