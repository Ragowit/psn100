<?php

declare(strict_types=1);

final readonly class DatabaseConfig
{
    public function __construct(
        private string $host,
        private string $database,
        private string $user,
        #[\SensitiveParameter]
        private string $password,
    ) {
    }

    #[\NoDiscard]
    public static function createDefault(): self
    {
        return new self('', '', '', '');
    }

    /**
     * @param array<string, string> $config
     */
    #[\NoDiscard]
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
    #[\NoDiscard]
    public static function fromEnvironment(array $environment = []): self
    {
        return new self(
            self::readConfigValue('DB_HOST', $environment),
            self::readConfigValue('DB_NAME', $environment),
            self::readConfigValue('DB_USER', $environment),
            self::readConfigValue('DB_PASSWORD', $environment),
        );
    }

    public function isComplete(): bool
    {
        return $this->host !== ''
            && $this->database !== ''
            && $this->user !== ''
            && $this->password !== '';
    }

    private static function readConfigValue(string $name, array $environment): string
    {
        $value = $environment[$name] ?? false;
        if (is_string($value)) {
            if ($name === 'DB_PASSWORD') {
                return $value;
            }

            if (trim($value) !== '') {
                return trim($value);
            }
        }

        $fallback = getenv($name);
        if (is_string($fallback)) {
            if ($name === 'DB_PASSWORD') {
                return $fallback;
            }

            if (trim($fallback) !== '') {
                return trim($fallback);
            }
        }

        return '';
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
    private readonly DatabaseConfig $config;

    /**
     * @var array<int, mixed>
     */
    private readonly array $options;

    public function __construct(?DatabaseConfig $config = null, array $options = [])
    {
        $this->config = $config ?? DatabaseConfig::createDefault();
        $this->options = $this->resolveOptions($options);

        if (!$this->config->isComplete()) {
            throw new DatabaseConnectionException(self::buildMissingConfigurationMessage());
        }

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

    #[\NoDiscard]
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

    private static function buildMissingConfigurationMessage(): string
    {
        return 'Database connection is not configured. Set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD '
            . 'environment variables. When using the PHP built-in server, pass -d variables_order=EGPCS '
            . 'or rely on getenv() by exporting the variables in your shell.';
    }
}
