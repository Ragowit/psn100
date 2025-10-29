<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobEntryPoint.php';
require_once __DIR__ . '/CronJobCliArguments.php';
require_once __DIR__ . '/../TrophyCalculator.php';
require_once __DIR__ . '/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobApplication
{
    private CronJobEntryPoint $entryPoint;

    private CronJobCliArguments $cliArguments;

    private function __construct(CronJobEntryPoint $entryPoint, CronJobCliArguments $cliArguments)
    {
        $this->entryPoint = $entryPoint;
        $this->cliArguments = $cliArguments;
    }

    /**
     * @param array<int, string>|string[] $argv
     */
    public static function fromGlobals(string $rootDirectory, array $argv = []): self
    {
        $entryPoint = CronJobEntryPoint::create($rootDirectory);
        $cliArguments = CronJobCliArguments::fromArgv($argv);

        return new self($entryPoint, $cliArguments);
    }

    public function run(): void
    {
        $workerId = $this->cliArguments->getWorkerId();

        $this->entryPoint->runWithFactory(function (PDO $database) use ($workerId): \CronJobInterface {
            $trophyCalculator = new TrophyCalculator($database);
            $logger = new Psn100Logger($database);

            return new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, $workerId);
        }, true);
    }
}
