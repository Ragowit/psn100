<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';

class SimpleRouteHandler implements RouteHandlerInterface
{
    private string $includeFile;

    private string $redirectPath;

    public function __construct(string $includeFile, string $redirectPath)
    {
        $this->includeFile = $includeFile;
        $this->redirectPath = $redirectPath;
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
