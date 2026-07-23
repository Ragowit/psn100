<?php

declare(strict_types=1);

enum JsonResponseStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
}
