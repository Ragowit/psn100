<?php

declare(strict_types=1);

enum PlayerQueueStatus: string
{
    case QUEUED = 'queued';
    case COMPLETE = 'complete';
    case ERROR = 'error';
}
