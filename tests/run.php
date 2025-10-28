<?php

declare(strict_types=1);

require __DIR__ . '/TestRunnerCommand.php';

$command = TestRunnerCommand::fromDirectory(__DIR__);

exit($command->execute());
