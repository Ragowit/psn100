<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class SimpleRouteHandler implements RouteHandlerInterface
{
    public function __construct(private string $includeFile, private string $redirectPath)
    {
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        if ($this->hasAdditionalSegments($segments)) {
            return RouteResult::redirect($this->redirectPath);
        }

        return RouteResult::include($this->includeFile);
    }

    /**
     * @param list<string> $segments
     */
    private function hasAdditionalSegments(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ($segment !== '') {
                return true;
            }
        }

        return false;
    }
}
