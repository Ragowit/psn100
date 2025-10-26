<?php

declare(strict_types=1);

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/HttpRequest.php';
require_once __DIR__ . '/TemplateRenderer.php';
require_once __DIR__ . '/RouteResultResponder.php';

class Application
{
    private Router $router;

    private HttpRequest $request;

    private RouteResultResponder $routeResultResponder;

    public function __construct(
        Router $router,
        HttpRequest $request,
        TemplateRenderer $templateRenderer,
        string $notFoundTemplate = '404.php',
        ?RouteResultResponder $routeResultResponder = null
    ) {
        $this->router = $router;
        $this->request = $request;
        $this->routeResultResponder = $routeResultResponder ?? new RouteResultResponder(
            $templateRenderer,
            $notFoundTemplate
        );
    }

    public function run(): void
    {
        $requestUri = $this->request->getResolvedUri();
        $routeResult = $this->router->dispatch($requestUri);

        $this->routeResultResponder->respond($routeResult);
    }
}
