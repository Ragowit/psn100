<?php

declare(strict_types=1);

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/RouteResult.php';
require_once __DIR__ . '/HttpRequest.php';

class Application
{
    private Router $router;

    private HttpRequest $request;

    private string $notFoundTemplate;

    public function __construct(Router $router, HttpRequest $request, string $notFoundTemplate = '404.php')
    {
        $this->router = $router;
        $this->request = $request;
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
            require_once $this->notFoundTemplate;
            exit();
        }

        if ($routeResult->shouldInclude()) {
            $variables = $routeResult->getVariables();

            if ($variables !== []) {
                extract($variables, EXTR_SKIP);
            }

            $include = $routeResult->getInclude();
            if ($include !== null) {
                // The included templates expect global variables like $database and $utility
                // to be available, just as they were when index.php handled the routing in
                // the global scope. Make them available here before including the template.
                global $database, $utility;
                require_once $include;
            }
        }
    }
}
