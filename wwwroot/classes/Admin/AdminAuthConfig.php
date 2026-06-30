<?php

declare(strict_types=1);

final readonly class AdminAuthConfig
{
    public function __construct(
        private string $username,
        private string $password,
        private string $passwordHash,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::readEnvironmentValue('ADMIN_USERNAME'),
            self::readEnvironmentValue('ADMIN_PASSWORD'),
            self::readEnvironmentValue('ADMIN_PASSWORD_HASH'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->username !== '' && ($this->passwordHash !== '' || $this->password !== '');
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function verifyPassword(string $password): bool
    {
        if ($this->passwordHash !== '') {
            return password_verify($password, $this->passwordHash);
        }

        return hash_equals($this->password, $password);
    }

    private static function readEnvironmentValue(string $name): string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
