<?php

declare(strict_types=1);

enum PlayerReportSubmitOutcome: string
{
    case SUCCESS = 'success';
    case DUPLICATE = 'duplicate';
    case LIMIT = 'limit';
}
