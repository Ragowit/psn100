<?php

declare(strict_types=1);

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/RouteResult.php';

class Application
{
    private Router $router;

    /**
     * @var array<string, mixed>
     */
    private array $server;

    private string $notFoundTemplate;

    /**
     * @param array<string, mixed> $server
     */
    public function __construct(Router $router, array $server, string $notFoundTemplate = '404.php')
    {
        $this->router = $router;
        $this->server = $server;
        $this->notFoundTemplate = $notFoundTemplate;
    }

    public function run(): void
    {
        $requestUri = $this->resolveRequestUri();
        $routeResult = $this->router->dispatch($requestUri);

        $this->handleRouteResult($routeResult);
    }

    private function resolveRequestUri(): string
    {
        // SCRIPT_URL isn't available in all web server configurations (for example,
        // the PHP built-in development server). Fall back to REQUEST_URI so routing
        // works everywhere without PHP notices.
        $scriptUrl = $this->server['SCRIPT_URL'] ?? null;
        if (is_string($scriptUrl) && $scriptUrl !== '') {
            return $scriptUrl;
        }

        $requestUri = $this->server['REQUEST_URI'] ?? '/';
        if (!is_string($requestUri) || $requestUri === '') {
            return '/';
        }

        return $requestUri;
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
