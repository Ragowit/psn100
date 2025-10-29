<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Application.php';
require_once __DIR__ . '/../wwwroot/classes/Router.php';
require_once __DIR__ . '/../wwwroot/classes/HttpRequest.php';
require_once __DIR__ . '/../wwwroot/classes/TemplateRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/RouteResult.php';
require_once __DIR__ . '/../wwwroot/classes/RouteResultResponder.php';

final class FakeRouter extends Router
{
    private RouteResult $routeResult;

    public ?string $dispatchedUri = null;

    public function __construct(RouteResult $routeResult)
    {
        $this->routeResult = $routeResult;
    }

    public function dispatch(string $requestUri): RouteResult
    {
        $this->dispatchedUri = $requestUri;

        return $this->routeResult;
    }
}

final class FixedHttpRequest extends HttpRequest
{
    private string $resolvedUri;

    public function __construct(string $resolvedUri)
    {
        $this->resolvedUri = $resolvedUri;
    }

    public function getResolvedUri(): string
    {
        return $this->resolvedUri;
    }
}

final class TemplateRendererSpy extends TemplateRenderer
{
    public ?string $lastTemplate = null;

    /**
     * @var array<string, mixed>
     */
    public array $lastVariables = [];

    public int $renderCalls = 0;

    public function __construct()
    {
        // Parent constructor intentionally not called.
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $templatePath, array $variables = []): void
    {
        $this->renderCalls++;
        $this->lastTemplate = $templatePath;
        $this->lastVariables = $variables;
    }
}

final class ApplicationTest extends TestCase
{
    public function testRunDispatchesResolvedUriAndRendersTemplate(): void
    {
        $routeResult = RouteResult::include('template.php', ['name' => 'Example']);
        $router = new FakeRouter($routeResult);
        $request = new FixedHttpRequest('/games/123?sort=recent');
        $templateRenderer = new TemplateRendererSpy();

        $application = new Application($router, $request, $templateRenderer);
        $application->run();

        $this->assertSame('/games/123?sort=recent', $router->dispatchedUri);
        $this->assertSame('template.php', $templateRenderer->lastTemplate);
        $this->assertSame(['name' => 'Example'], $templateRenderer->lastVariables);
        $this->assertSame(1, $templateRenderer->renderCalls);
    }

    public function testConstructorUsesProvidedRouteResultResponder(): void
    {
        $routeResult = RouteResult::include('template.php');
        $router = new FakeRouter($routeResult);
        $request = new FixedHttpRequest('/');
        $templateRenderer = new TemplateRendererSpy();
        $providedResponder = new RouteResultResponder($templateRenderer, 'custom-not-found.php');

        $application = new Application($router, $request, $templateRenderer, 'unused-not-found.php', $providedResponder);

        $reflection = new ReflectionClass($application);
        $property = $reflection->getProperty('routeResultResponder');
        $property->setAccessible(true);

        $this->assertSame($providedResponder, $property->getValue($application));
    }
}
