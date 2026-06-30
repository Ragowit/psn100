<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/AdminAuthService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/AdminUserRepository.php';
require_once __DIR__ . '/../wwwroot/classes/SessionManager.php';

final class AdminAuthServiceTest extends TestCase
{
    private PDO $pdo;

    private AdminUserRepository $repository;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_start();
        $_SESSION = [];

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

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testLoginSucceedsWithDatabaseCredentials(): void
    {
        $this->insertAdmin('admin-user', password_hash('secret-password', PASSWORD_DEFAULT));
        $service = new AdminAuthService($this->repository);

        $this->assertTrue($service->login('admin-user', 'secret-password'));
        $this->assertTrue($service->isAuthenticated());
        $this->assertSame('admin-user', $service->getAuthenticatedUsername());
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $this->insertAdmin('admin-user', password_hash('secret-password', PASSWORD_DEFAULT));
        $service = new AdminAuthService($this->repository);

        $this->assertFalse($service->login('admin-user', 'wrong-password'));
        $this->assertFalse($service->isAuthenticated());
    }

    public function testIsConfiguredRequiresAtLeastOneAdminUser(): void
    {
        $service = new AdminAuthService($this->repository);

        $this->assertFalse($service->isConfigured());

        $this->insertAdmin('admin-user', password_hash('secret-password', PASSWORD_DEFAULT));

        $this->assertTrue($service->isConfigured());
    }

    public function testLogoutClearsAuthenticationState(): void
    {
        $this->insertAdmin('admin-user', password_hash('secret-password', PASSWORD_DEFAULT));
        $service = new AdminAuthService($this->repository);
        $service->login('admin-user', 'secret-password');

        $service->logout();

        $this->assertFalse($service->isAuthenticated());
        $this->assertSame(null, $service->getAuthenticatedUsername());
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
