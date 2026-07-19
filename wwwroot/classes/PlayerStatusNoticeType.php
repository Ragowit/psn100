<?php

declare(strict_types=1);

enum PlayerStatusNoticeType: string
{
    case Flagged = 'flagged';
    case Private = 'private';
}
