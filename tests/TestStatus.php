<?php

declare(strict_types=1);

enum TestStatus: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';
    case ERROR = 'error';
}
