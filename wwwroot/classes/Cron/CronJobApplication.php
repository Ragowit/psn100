<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';
require_once __DIR__ . '/CronJobRunner.php';

/**
 * Small helper that encapsulates the procedural bootstrap that each cron
 * entrypoint previously performed. By funnelling the work through this class
 * the scripts become easier to read and are now focused solely on describing
 * which job to execute.
 */
final class CronJobApplication
{
    private CronJobRunner $runner;

    private function __construct(CronJobRunner $runner)
    {
        $this->runner = $runner;
    }

    public static function create(?CronJobRunner $runner = null): self
    {
        return new self($runner ?? CronJobRunner::create());
    }

    public function configureEnvironment(): void
    {
        $this->runner->configureEnvironment();
    }

    /**
     * @param callable():CronJobInterface $jobFactory
     */
    public function run(callable $jobFactory): void
    {
        $job = $jobFactory();

        if (!$job instanceof CronJobInterface) {
            throw new InvalidArgumentException('Cron job factory must return a CronJobInterface instance.');
        }

        $this->runner->run($job);
    }
}
