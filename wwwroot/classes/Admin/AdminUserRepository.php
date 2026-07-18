<?php

declare(strict_types=1);

final class AdminUserRepository
{
    private const string SQL_ADMIN_COUNT = <<<'SQL'
        SELECT
            COUNT(*)
        FROM
            admin_user
        SQL;
    private const string SQL_PASSWORD_HASH_BY_USERNAME = <<<'SQL'
        SELECT
            password_hash
        FROM
            admin_user
        WHERE
            username = :username
        LIMIT 1
        SQL;

    public function __construct(private readonly PDO $database)
    {
    }

    public function hasAnyAdmin(): bool
    {
        return (int) $this->database->query(self::SQL_ADMIN_COUNT)->fetchColumn() > 0;
    }

    public function verifyCredentials(string $username, #[\SensitiveParameter] string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }

        $statement = $this->database->prepare(self::SQL_PASSWORD_HASH_BY_USERNAME);
        $statement->bindValue(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $passwordHash = $statement->fetchColumn();
        if ($passwordHash === false) {
            return false;
        }

        return password_verify($password, (string) $passwordHash);
    }
}
