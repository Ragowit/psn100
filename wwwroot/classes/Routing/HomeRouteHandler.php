<?php

declare(strict_types=1);

require_once __DIR__ . '/RouteHandlerInterface.php';

final readonly class HomeRouteHandler implements RouteHandlerInterface
{
    public function __construct(private string $includeFile = 'home.php')
    {
    }

    /**
     * @param list<string> $segments
     */
    #[\Override]
    public function handle(array $segments): RouteResult
    {
        return RouteResult::include($this->includeFile);
    }
}
