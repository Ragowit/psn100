<?php

declare(strict_types=1);

final class IpSubmissionLockExecutor
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function execute(string $ipAddress, callable $callback): mixed
    {
        if (!$this->supportsNamedLocks()) {
            return $callback();
        }

        $lockName = $this->buildLockName($ipAddress);
        $lockStatement = $this->database->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lockStatement->bindValue(':lock_name', $lockName, PDO::PARAM_STR);
        $lockStatement->execute();

        $lockAcquired = (int) ($lockStatement->fetchColumn() ?? 0) === 1;
        if (!$lockAcquired) {
            throw new RuntimeException('Unable to acquire IP submission lock.');
        }

        try {
            return $callback();
        } finally {
            $releaseStatement = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $releaseStatement->bindValue(':lock_name', $lockName, PDO::PARAM_STR);
            $releaseStatement->execute();
        }
    }

    private function supportsNamedLocks(): bool
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private function buildLockName(string $ipAddress): string
    {
        return 'psn100:ip:' . hash('sha256', $ipAddress);
    }
}
