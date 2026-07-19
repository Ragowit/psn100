<?php

declare(strict_types=1);

enum PlayerScanCompletionStatus: string
{
    case Completed = 'completed';
    case ContinueScan = 'continue_scan';
}
