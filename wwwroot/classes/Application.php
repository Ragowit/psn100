<?php

declare(strict_types=1);

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/RouteResult.php';
require_once __DIR__ . '/HttpRequest.php';
require_once __DIR__ . '/TemplateRenderer.php';

class Application
{
    private Router $router;

    private HttpRequest $request;

    private TemplateRenderer $templateRenderer;

    private string $notFoundTemplate;

    public function __construct(
        Router $router,
        HttpRequest $request,
        TemplateRenderer $templateRenderer,
        string $notFoundTemplate = '404.php'
    )
    {
        $this->router = $router;
        $this->request = $request;
        $this->templateRenderer = $templateRenderer;
        $this->notFoundTemplate = $notFoundTemplate;
    }

    public function run(): void
    {
        $requestUri = $this->request->getResolvedUri();
        $routeResult = $this->router->dispatch($requestUri);

        $this->handleRouteResult($routeResult);
    }

    private function handleRouteResult(RouteResult $routeResult): void
    {
        if ($routeResult->shouldRedirect()) {
            $location = $routeResult->getRedirect() ?? '/';
            $statusCode = $routeResult->getStatusCode() ?? 303;

            header('Location: ' . $location, true, $statusCode);
            exit();
        }

        if ($routeResult->isNotFound()) {
            $statusCode = $routeResult->getStatusCode() ?? 404;
            http_response_code($statusCode);
            $this->templateRenderer->render($this->notFoundTemplate);
            exit();
        }

        if ($routeResult->shouldInclude()) {
            $include = $routeResult->getInclude();
            if ($include !== null) {
                $this->templateRenderer->render($include, $routeResult->getVariables());
            }
        }
    }
}
