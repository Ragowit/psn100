<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPagePlayer.php';
require_once __DIR__ . '/AboutPageScanSummary.php';

interface AboutPageDataProviderInterface
{
    public function getScanSummary(): AboutPageScanSummary;

    /**
     * @return list<AboutPagePlayer>
     */
    public function getScanLogPlayers(int $limit): array;
}
