<?php

declare(strict_types=1);

enum AdminStreamEventType: string
{
    case Progress = 'progress';
    case Log = 'log';
    case Complete = 'complete';
    case Error = 'error';
}
