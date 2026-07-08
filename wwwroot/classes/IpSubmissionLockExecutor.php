<?php

declare(strict_types=1);

require_once __DIR__ . '/IpSubmissionLockUnavailableException.php';

class IpSubmissionLockExecutor
{
    private const string LOCK_NAME_PREFIX = 'psn100:ip:';

    private const int MYSQL_LOCK_NAME_MAX_LENGTH = 64;

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
            throw new IpSubmissionLockUnavailableException('Unable to acquire IP submission lock.');
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
        $digestLength = self::MYSQL_LOCK_NAME_MAX_LENGTH - strlen(self::LOCK_NAME_PREFIX);

        return self::LOCK_NAME_PREFIX . substr(hash('sha256', $ipAddress), 0, $digestLength);
    }
}
