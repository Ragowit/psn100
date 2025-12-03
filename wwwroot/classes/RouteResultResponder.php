<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteResult.php';
require_once __DIR__ . '/TemplateRenderer.php';

/**
 * Handles emitting HTTP responses for a given {@see RouteResult}.
 *
 * By encapsulating the behaviour in a dedicated class we make it easier to
 * unit test and reuse response emitting logic while keeping the
 * {@see Application} class focused on routing concerns.
 */
final readonly class RouteResultResponder
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private string $notFoundTemplate = '404.php'
    ) {
    }

    public function respond(RouteResult $routeResult): void
    {
        if ($routeResult->shouldRedirect()) {
            $this->emitRedirect(
                $routeResult->getRedirect() ?? '/',
                $routeResult->getStatusCode() ?? 303
            );

            return;
        }

        if ($routeResult->isNotFound()) {
            $this->emitNotFound($routeResult->getStatusCode() ?? 404);

            return;
        }

        if ($routeResult->shouldInclude()) {
            $include = $routeResult->getInclude();
            if ($include !== null) {
                $this->emitTemplate($include, $routeResult->getVariables());
            }
        }
    }

    private function emitRedirect(string $location, int $statusCode): void
    {
        header('Location: ' . $location, true, $statusCode);
        exit();
    }

    private function emitNotFound(int $statusCode): void
    {
        http_response_code($statusCode);
        $this->templateRenderer->render($this->notFoundTemplate);
        exit();
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function emitTemplate(string $template, array $variables): void
    {
        $this->templateRenderer->render($template, $variables);
    }
}

