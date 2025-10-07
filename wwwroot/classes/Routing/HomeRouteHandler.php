<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';

class HomeRouteHandler implements RouteHandlerInterface
{
    private string $includeFile;

    public function __construct(string $includeFile = 'home.php')
    {
        $this->includeFile = $includeFile;
    }

    /**
     * @param list<string> $segments
     */
    public function handle(array $segments): RouteResult
    {
        return RouteResult::include($this->includeFile);
    }
}
