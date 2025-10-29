<?php

declare(strict_types=1);

final class DatabaseConfig
{
    private string $host;

    private string $database;

    private string $user;

    private string $password;

    public function __construct(string $host, string $database, string $user, string $password)
    {
        $this->host = $host;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
    }

    public static function createDefault(): self
    {
        return new self('', '', '', '');
    }

    /**
     * @param array<string, string> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            (string) ($config['host'] ?? ''),
            (string) ($config['database'] ?? ''),
            (string) ($config['user'] ?? ''),
            (string) ($config['password'] ?? '')
        );
    }

    /**
     * @param array<string, string> $environment
     */
    public static function fromEnvironment(array $environment = []): self
    {
        return new self(
            (string) ($environment['DB_HOST'] ?? ''),
            (string) ($environment['DB_NAME'] ?? ''),
            (string) ($environment['DB_USER'] ?? ''),
            (string) ($environment['DB_PASSWORD'] ?? '')
        );
    }

    public function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->database
        );
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}

final class DatabaseConnectionException extends RuntimeException
{
}

class Database extends PDO
{
    private DatabaseConfig $config;

    /**
     * @var array<int, mixed>
     */
    private array $options;

    public function __construct(?DatabaseConfig $config = null, array $options = [])
    {
        $this->config = $config ?? DatabaseConfig::createDefault();
        $this->options = $this->resolveOptions($options);

        try {
            parent::__construct(
                $this->config->getDsn(),
                $this->config->getUser(),
                $this->config->getPassword(),
                $this->options
            );

            $this->configureSession();
        } catch (PDOException $exception) {
            throw new DatabaseConnectionException('Unable to connect to the database.', 0, $exception);
        }
    }

    public static function fromEnvironment(array $environment = []): self
    {
        return new self(DatabaseConfig::fromEnvironment($environment));
    }

    public function getConfig(): DatabaseConfig
    {
        return $this->config;
    }

    /**
     * @return array<int, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<int, mixed> $options
     * @return array<int, mixed>
     */
    private function resolveOptions(array $options): array
    {
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
        ];

        return $options + $defaultOptions;
    }

    private function configureSession(): void
    {
        if ($this->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        try {
            $this->exec("SET SESSION optimizer_switch = 'with_clause=merged'");
        } catch (PDOException $exception) {
            // The optimizer switch is an optional hint; ignore failures so connections still succeed.
        }
    }
}
