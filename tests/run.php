<?php

declare(strict_types=1);

require __DIR__ . '/TestRunner.php';

$runner = TestRunner::fromDirectory(__DIR__);

exit($runner->run());
