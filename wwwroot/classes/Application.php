<?php

declare(strict_types=1);

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/HttpRequest.php';
require_once __DIR__ . '/TemplateRenderer.php';
require_once __DIR__ . '/RouteResultResponder.php';

class Application
{
    private readonly RouteResultResponder $routeResultResponder;

    public function __construct(
        private readonly Router $router,
        private readonly HttpRequest $request,
        private readonly TemplateRenderer $templateRenderer,
        private readonly string $notFoundTemplate = '404.php',
        ?RouteResultResponder $routeResultResponder = null,
    ) {
        $this->routeResultResponder = $routeResultResponder ?? new RouteResultResponder(
            $this->templateRenderer,
            $this->notFoundTemplate
        );
    }

    public function run(): void
    {
        $requestUri = $this->request->getResolvedUri();
        $routeResult = $this->router->dispatch($requestUri);

        $this->routeResultResponder->respond($routeResult);
    }
}
