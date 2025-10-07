<?php

declare(strict_types=1);

require_once __DIR__ . '/../RouteResult.php';

interface RouteHandlerInterface
{
    /**
     * @param list<string> $segments
     */
    public function handle(array $segments): RouteResult;
}
