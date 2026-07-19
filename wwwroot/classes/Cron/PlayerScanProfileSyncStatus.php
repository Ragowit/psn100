<?php

declare(strict_types=1);

enum PlayerScanProfileSyncStatus: string
{
    case Success = 'success';
    case SkipPlayer = 'skip_player';
}
