<?php

declare(strict_types=1);

enum PlayerScanTrophySummaryAccessStatus: string
{
    case Accessible = 'accessible';
    case Private = 'private';
    case AbortScan = 'abort_scan';
}
