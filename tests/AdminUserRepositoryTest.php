<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/AdminUserRepository.php';

final class AdminUserRepositoryTest extends TestCase
{
    private PDO $pdo;

    private AdminUserRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE admin_user (
                username TEXT PRIMARY KEY,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );

        $this->repository = new AdminUserRepository($this->pdo);
    }

    public function testHasAnyAdminReturnsFalseWhenTableIsEmpty(): void
    {
        $this->assertFalse($this->repository->hasAnyAdmin());
    }

    public function testHasAnyAdminReturnsTrueWhenAtLeastOneUserExists(): void
    {
        $this->insertAdmin('admin', password_hash('secret', PASSWORD_DEFAULT));

        $this->assertTrue($this->repository->hasAnyAdmin());
    }

    public function testVerifyCredentialsAcceptsMatchingPassword(): void
    {
        $this->insertAdmin('admin-user', password_hash('secret-password', PASSWORD_DEFAULT));

        $this->assertTrue($this->repository->verifyCredentials('admin-user', 'secret-password'));
    }

    public function testVerifyCredentialsRejectsInvalidPasswordOrUnknownUser(): void
    {
        $this->insertAdmin('admin-user', password_hash('secret-password', PASSWORD_DEFAULT));

        $this->assertFalse($this->repository->verifyCredentials('admin-user', 'wrong-password'));
        $this->assertFalse($this->repository->verifyCredentials('missing-user', 'secret-password'));
        $this->assertFalse($this->repository->verifyCredentials('', 'secret-password'));
    }

    private function insertAdmin(string $username, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_user (username, password_hash) VALUES (:username, :password_hash)'
        );
        $statement->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
        ]);
    }
}
