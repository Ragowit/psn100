<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobBootstrapper.php';
require_once __DIR__ . '/CronJobInterface.php';

/**
 * Small helper that encapsulates the procedural bootstrapping performed by
 * each cron entry script.  By funnelling the duplicated logic through this
 * class the entry scripts themselves can stay focused on describing which
 * job should run while this class takes care of configuring the environment
 * and validating the job factory.
 */
final class CronJobEntryPoint
{
    private CronJobBootstrapper $bootstrapper;

    private function __construct(CronJobBootstrapper $bootstrapper)
    {
        $this->bootstrapper = $bootstrapper;
    }

    public static function create(string $projectRoot, ?CronJobBootstrapper $bootstrapper = null): self
    {
        return new self($bootstrapper ?? CronJobBootstrapper::create($projectRoot));
    }

    /**
     * Convenience wrapper for cron jobs whose constructor only requires the
     * PDO connection provided by the bootstrapper.
     *
     * @param class-string<CronJobInterface> $cronJobClass
     */
    public function runJob(string $cronJobClass, bool $loadComposerAutoload = false): void
    {
        if (!is_subclass_of($cronJobClass, CronJobInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Cron job class "%s" must implement %s.',
                $cronJobClass,
                CronJobInterface::class
            ));
        }

        $this->runWithFactory(
            static fn(\PDO $database): CronJobInterface => new $cronJobClass($database),
            $loadComposerAutoload
        );
    }

    /**
     * @param callable(\PDO):CronJobInterface $factory
     */
    public function runWithFactory(callable $factory, bool $loadComposerAutoload = false): void
    {
        $this->bootstrapper->bootstrap($loadComposerAutoload);

        $this->bootstrapper->run(static function (\PDO $database) use ($factory): CronJobInterface {
            $job = $factory($database);

            if (!$job instanceof CronJobInterface) {
                throw new InvalidArgumentException('Cron job factory must return a CronJobInterface instance.');
            }

            return $job;
        });
    }
}
