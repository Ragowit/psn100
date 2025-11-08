<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobApplication.php';

final class CronJobBootstrapper
{
    private CronJobApplication $application;

    private string $projectRoot;

    private ?\PDO $database = null;

    private function __construct(string $projectRoot, CronJobApplication $application)
    {
        $this->projectRoot = rtrim($projectRoot, '/\\');
        $this->application = $application;
    }

    public static function create(string $projectRoot, ?CronJobApplication $application = null): self
    {
        return new self($projectRoot, $application ?? CronJobApplication::create());
    }

    public function bootstrap(bool $loadComposerAutoload = false): void
    {
        $this->application->configureEnvironment();

        if ($loadComposerAutoload) {
            $this->requireProjectFile('classes/PsnApi/autoload.php');
        }

        $initScript = $this->getProjectFilePath('init.php');
        require $initScript;

        if (!isset($database)) {
            throw new \RuntimeException('The init script did not define a $database variable.');
        }

        if (!$database instanceof \PDO) {
            throw new \RuntimeException('The $database variable defined by the init script must be a PDO instance.');
        }

        $this->database = $database;
    }

    /**
     * @param callable(\PDO): CronJobInterface $jobFactory
     */
    public function run(callable $jobFactory): void
    {
        $database = $this->getDatabase();

        $this->application->run(static function () use ($jobFactory, $database): CronJobInterface {
            return $jobFactory($database);
        });
    }

    public function getDatabase(): \PDO
    {
        if (!$this->database instanceof \PDO) {
            throw new \RuntimeException('CronJobBootstrapper::bootstrap() must be called before accessing the database.');
        }

        return $this->database;
    }

    private function requireProjectFile(string $relativePath, bool $requireOnce = true): void
    {
        $fullPath = $this->getProjectFilePath($relativePath);
        if ($requireOnce) {
            require_once $fullPath;
            return;
        }

        require $fullPath;
    }

    private function getProjectFilePath(string $relativePath): string
    {
        return $this->projectRoot . '/' . ltrim($relativePath, '/\\');
    }
}
